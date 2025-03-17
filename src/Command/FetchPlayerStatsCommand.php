<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Player;
use App\Repository\PlayerRepository;
use App\Service\PandaScoreService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:fetch-player-stats',
    description: 'Fetch statistics for CS:GO players from PandaScore API with parallel processing'
)]
class FetchPlayerStatsCommand extends Command
{
    /**
     * API endpoint URL pattern for player stats
     */
    private const API_URL = 'https://api.pandascore.co/csgo/players/%s/stats';

    /**
     * Default batch size for processing players
     */
    private const BATCH_SIZE = 10;

    /**
     * Maximum concurrent requests
     */
    private const MAX_CONCURRENT_REQUESTS = 5;

    /**
     * API rate limit (requests per second)
     */
    private const RATE_LIMIT = 2;

    /**
     * Memory threshold in MB
     */
    private const MEMORY_THRESHOLD = 150;

    public function __construct(
        private readonly PandaScoreService $pandaScoreService,
        private readonly HttpClientInterface $httpClient,
        private readonly PlayerRepository $playerRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        #[Autowire('%pandascore.token%')]
        private readonly string $pandascoreToken,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Number of players to process in one batch', self::BATCH_SIZE)
            ->addOption('offset', 'o', InputOption::VALUE_REQUIRED, 'Starting offset (skip first N players)', 0)
            ->addOption('max-players', 'm', InputOption::VALUE_REQUIRED, 'Maximum number of players to process', PHP_INT_MAX)
            ->addOption('concurrent', 'c', InputOption::VALUE_REQUIRED, 'Maximum concurrent requests', self::MAX_CONCURRENT_REQUESTS)
            ->addOption('id', 'i', InputOption::VALUE_REQUIRED, 'Process only a specific player ID', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Fetching statistics for CS:GO players from PandaScore API with parallel processing');

        // Set memory limit to handle large responses
        ini_set('memory_limit', '512M');

        $batchSize = (int)$input->getOption('batch-size');
        $offset = (int)$input->getOption('offset');
        $maxPlayers = (int)$input->getOption('max-players');
        $maxConcurrent = (int)$input->getOption('concurrent');
        $specificId = $input->getOption('id');
        $isDebug = $this->environment === 'dev';

        $this->logger->info('Starting player stats fetch operation', [
            'batch_size' => $batchSize,
            'offset' => $offset,
            'max_players' => $maxPlayers,
            'max_concurrent' => $maxConcurrent,
            'specific_id' => $specificId
        ]);

        // If a specific player ID is provided, process just that one
        if ($specificId !== null) {
            $player = $this->playerRepository->find($specificId);
            if (!$player) {
                $io->error(sprintf('Player with ID %s not found', $specificId));
                return Command::FAILURE;
            }

            $io->section(sprintf('Processing player: %s (ID: %d)', $player->getName(), $player->getId()));
            $result = $this->processPlayer($player, $io);

            if ($result) {
                $io->success('Player stats updated successfully');
            } else {
                $io->error('Failed to update player stats');
                return Command::FAILURE;
            }

            return Command::SUCCESS;
        }

        // Get total players for progress reporting
        $totalPlayers = $this->playerRepository->countAll();
        if (!$totalPlayers) {
            $io->warning('No players found.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d players in total', $totalPlayers));
        $totalProcessed = 0;
        $successCount = 0;
        $failureCount = 0;
        $batchNumber = 0;

        $progressBar = $io->createProgressBar(min($totalPlayers, $maxPlayers));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        // Process players in batches
        while ($offset < $totalPlayers && $totalProcessed < $maxPlayers) {
            $batchNumber++;

            // Check memory usage before loading a new batch
            $memoryUsage = memory_get_usage(true) / 1024 / 1024;

            if ($isDebug) {
                $io->info(sprintf('Memory usage before batch %d: %.2f MB', $batchNumber, $memoryUsage));
            }

            if ($memoryUsage > self::MEMORY_THRESHOLD) {
                $io->warning('Memory threshold reached, pausing for memory cleanup');
                $this->logger->warning('Memory threshold reached during player stats fetch', [
                    'memory_usage_mb' => $memoryUsage,
                    'threshold_mb' => self::MEMORY_THRESHOLD
                ]);

                // Force garbage collection
                gc_collect_cycles();
                sleep(2); // Give the system some time to free memory
                continue;
            }

            // Determine current batch size (respecting the max players limit)
            $currentBatchSize = min($batchSize, $maxPlayers - $totalProcessed);

            if ($isDebug) {
                $io->section(sprintf('Processing batch %d: %d players (offset: %d)',
                    $batchNumber,
                    $currentBatchSize,
                    $offset
                ));
            }

            try {
                // Load player data for batch
                $playerRows = $this->loadPlayers($currentBatchSize, $offset);

                if (empty($playerRows)) {
                    $io->warning('No more players found.');
                    break;
                }

                // Process the batch with parallel requests
                $batchResults = $this->processPlayersBatch($playerRows, $maxConcurrent, $io, $progressBar);

                $batchProcessed = $batchResults['total'];
                $batchSuccess = $batchResults['success'];
                $batchFailure = $batchResults['failure'];

                $totalProcessed += $batchProcessed;
                $successCount += $batchSuccess;
                $failureCount += $batchFailure;

                $offset += $currentBatchSize;

                if ($isDebug) {
                    $io->success(sprintf(
                        'Batch %d completed. Success: %d, Failures: %d. Total processed so far: %d/%d (%.1f%%)',
                        $batchNumber,
                        $batchSuccess,
                        $batchFailure,
                        $totalProcessed,
                        min($totalPlayers, $maxPlayers),
                        ($totalProcessed / min($totalPlayers, $maxPlayers)) * 100
                    ));
                }

                // Clear entity manager to free memory
                $this->entityManager->clear();

                // Force garbage collection
                gc_collect_cycles();

                // Sleep between batches to allow system to recover
                usleep(500000); // 500ms
            } catch (\Exception $e) {
                $this->logger->error('Error processing batch', [
                    'batch' => $batchNumber,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                $io->error(sprintf('Error processing batch %d: %s', $batchNumber, $e->getMessage()));

                // Continue with next batch rather than failing completely
                $offset += $currentBatchSize;
                $failureCount += $currentBatchSize;
                $progressBar->advance($currentBatchSize);
            }
        }

        $progressBar->finish();
        $io->newLine(2);

        if ($failureCount > 0) {
            $io->warning(sprintf('%d players failed to process', $failureCount));
        }

        $io->success(sprintf('Processing complete. Successfully processed %d/%d players.',
            $successCount,
            $totalProcessed
        ));

        $this->logger->info('Player stats fetch operation completed', [
            'total_processed' => $totalProcessed,
            'successes' => $successCount,
            'failures' => $failureCount
        ]);

        return Command::SUCCESS;
    }

    /**
     * Load players for batch processing
     */
    private function loadPlayers(int $limit, int $offset): array
    {
        $conn = $this->entityManager->getConnection();
        return $conn->fetchAllAssociative(
            'SELECT id, name, pandascore_id FROM player ORDER BY id ASC LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    /**
     * Process a batch of players with parallel requests
     */
    private function processPlayersBatch(array $playerRows, int $maxConcurrent, SymfonyStyle $io, $progressBar): array
    {
        $pendingRequests = [];
        $activeRequests = [];
        $playerMap = [];
        $processed = 0;
        $success = 0;
        $failure = 0;
        $lastRequestTime = 0;
        $requestId = 0;
        $isDebug = $this->environment === 'dev';

        // Prepare requests for all players in the batch
        foreach ($playerRows as $playerData) {
            if (empty($playerData['pandascore_id'])) {
                $io->warning(sprintf('Player %s has no PandaScore ID, skipping', $playerData['name']));
                $processed++;
                $failure++;
                $progressBar->advance();
                continue;
            }

            $pendingRequests[] = [
                'player_id' => $playerData['id'],
                'name' => $playerData['name'],
                'url' => sprintf(self::API_URL, $playerData['pandascore_id'])
            ];
        }

        // Process requests in parallel
        while (!empty($pendingRequests) || !empty($activeRequests)) {
            // Monitor memory usage
            if ($processed > 0 && $processed % 10 === 0) {
                $memoryUsage = memory_get_usage(true) / 1024 / 1024;

                if ($isDebug) {
                    $io->note(sprintf('Memory usage during processing: %.2f MB', $memoryUsage));
                }

                if ($memoryUsage > self::MEMORY_THRESHOLD) {
                    $io->warning('High memory usage, forcing garbage collection');
                    gc_collect_cycles();
                    sleep(1); // Brief pause to let system recover
                }
            }

            // Add new requests up to the concurrent limit
            $now = microtime(true);
            while (!empty($pendingRequests) && count($activeRequests) < $maxConcurrent) {
                // Respect rate limit
                if ($now - $lastRequestTime < (1 / self::RATE_LIMIT)) {
                    $sleepTime = (1 / self::RATE_LIMIT) - ($now - $lastRequestTime);
                    usleep((int)($sleepTime * 1_000_000));
                    $now = microtime(true);
                }

                $request = array_shift($pendingRequests);
                $playerId = $request['player_id'];
                $playerName = $request['name'];

                try {
                    $currentRequestId = "request_" . $requestId++;

                    if ($isDebug) {
                        $io->text(sprintf('Sending request for player: %s (ID: %s)', $playerName, $playerId));
                    }

                    $response = $this->httpClient->request('GET', $request['url'], [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->pandascoreToken,
                            'Accept' => 'application/json',
                        ],
                        'timeout' => 30,
                        'user_data' => $currentRequestId,
                    ]);

                    $activeRequests[$currentRequestId] = $response;
                    $playerMap[$currentRequestId] = $playerId;
                    $lastRequestTime = microtime(true);
                } catch (\Exception $e) {
                    $this->logger->error('Error requesting player stats', [
                        'player_id' => $playerId,
                        'player_name' => $playerName,
                        'error' => $e->getMessage(),
                    ]);

                    if ($isDebug) {
                        $io->error(sprintf('Error requesting stats for player %s: %s', $playerName, $e->getMessage()));
                    }

                    $processed++;
                    $failure++;
                    $progressBar->advance();
                }
            }

            // Check for completed requests
            if (!empty($activeRequests)) {
                foreach ($this->httpClient->stream($activeRequests) as $response => $chunk) {
                    if ($chunk->isLast()) {
                        try {
                            // Get request ID and player ID
                            $id = $response->getInfo('user_data');
                            $playerId = $playerMap[$id];

                            // Clean up tracking
                            unset($activeRequests[$id]);
                            unset($playerMap[$id]);

                            $statusCode = $response->getStatusCode();
                            if ($statusCode !== 200) {
                                throw new \RuntimeException("API responded with status code $statusCode");
                            }

                            $data = json_decode($response->getContent(), true);

                            if (!is_array($data)) {
                                throw new \RuntimeException("Invalid JSON response");
                            }

                            // Load full player entity
                            $player = $this->playerRepository->find($playerId);
                            if (!$player) {
                                throw new \RuntimeException("Player not found in database");
                            }

                            // Process player stats data
                            $result = $this->pandaScoreService->processPlayerStats($player, $data);

                            if ($result) {
                                if ($isDebug) {
                                    $io->text(sprintf('<info>✓</info> Stats updated for player %s', $player->getName()));
                                }
                                $success++;
                            } else {
                                if ($isDebug) {
                                    $io->text(sprintf('<comment>⚠</comment> Failed to update stats for player %s', $player->getName()));
                                }
                                $failure++;
                            }

                            $processed++;
                            $progressBar->advance();

                            // Clean up for memory savings
                            $data = null;
                            $player = null;
                        } catch (\Exception $e) {
                            $this->logger->error('Error processing player stats response', [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);

                            if ($isDebug) {
                                $io->error(sprintf('Error processing stats: %s', $e->getMessage()));
                            }

                            $processed++;
                            $failure++;
                            $progressBar->advance();
                        }
                    }
                }
            } else if (empty($pendingRequests)) {
                // No active requests and no pending requests - we're done
                break;
            } else {
                // No active requests but pending requests remain - brief pause
                usleep(50000); // 50ms
            }
        }

        return [
            'total' => $processed,
            'success' => $success,
            'failure' => $failure
        ];
    }

    /**
     * Process a single player
     */
    private function processPlayer(Player $player, SymfonyStyle $io): bool
    {
        if (empty($player->getPandascoreId())) {
            $io->warning(sprintf('Player %s has no PandaScore ID, skipping', $player->getName()));
            return false;
        }

        try {
            $url = sprintf(self::API_URL, $player->getPandascoreId());

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->pandascoreToken,
                    'Accept' => 'application/json',
                ],
                'timeout' => 30,
            ]);

            $data = json_decode($response->getContent(), true);

            if (!is_array($data)) {
                throw new \RuntimeException("Invalid JSON response");
            }

            $result = $this->pandaScoreService->processPlayerStats($player, $data);

            if ($result) {
                $io->text(sprintf('<info>✓</info> Stats updated for player %s', $player->getName()));
                return true;
            } else {
                $io->text(sprintf('<comment>⚠</comment> Failed to update stats for player %s', $player->getName()));
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error processing player stats', [
                'player_id' => $player->getId(),
                'player_name' => $player->getName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $io->error(sprintf('Error fetching stats for player %s: %s', $player->getName(), $e->getMessage()));
            return false;
        }
    }
}