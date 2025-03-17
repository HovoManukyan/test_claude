<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Team;
use App\Repository\TeamRepository;
use App\Service\PandaScoreService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:fetch-team-stats',
    description: 'Fetch team stats from PandaScore API with parallel processing'
)]
class FetchTeamStatsCommand extends Command
{
    /**
     * API endpoint URL pattern for team stats
     */
    private const API_URL = 'https://api.pandascore.co/csgo/teams/%s/stats';

    /**
     * Default batch size for processing teams
     */
    private const BATCH_SIZE = 50;

    /**
     * Maximum concurrent requests
     */
    private const MAX_CONCURRENT_REQUESTS = 10;

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
        private readonly TeamRepository $teamRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly string $pandascoreToken,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Number of teams to process in one batch', self::BATCH_SIZE)
            ->addOption('offset', 'o', InputOption::VALUE_REQUIRED, 'Starting offset (skip first N teams)', 0)
            ->addOption('max-teams', 'm', InputOption::VALUE_REQUIRED, 'Maximum number of teams to process', PHP_INT_MAX)
            ->addOption('concurrent', 'c', InputOption::VALUE_REQUIRED, 'Maximum concurrent requests', self::MAX_CONCURRENT_REQUESTS)
            ->addOption('id', 'i', InputOption::VALUE_REQUIRED, 'Process only a specific team ID', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Fetching team stats from PandaScore API with parallel processing');

        // Set memory limit to handle large responses
        ini_set('memory_limit', '2048M');

        $batchSize = (int)$input->getOption('batch-size');
        $offset = (int)$input->getOption('offset');
        $maxTeams = (int)$input->getOption('max-teams');
        $maxConcurrent = (int)$input->getOption('concurrent');
        $specificId = $input->getOption('id');

        $this->logger->info('Starting team stats fetch operation', [
            'batch_size' => $batchSize,
            'offset' => $offset,
            'max_teams' => $maxTeams,
            'max_concurrent' => $maxConcurrent,
            'specific_id' => $specificId
        ]);

        // If a specific team ID is provided, process just that one
        if ($specificId !== null) {
            $team = $this->teamRepository->find($specificId);
            if (!$team) {
                $io->error(sprintf('Team with ID %s not found', $specificId));
                return Command::FAILURE;
            }

            $io->section(sprintf('Processing team: %s (ID: %d)', $team->getName(), $team->getId()));
            $result = $this->processTeam($team, $io);

            if ($result) {
                $io->success('Team stats updated successfully');
            } else {
                $io->error('Failed to update team stats');
                return Command::FAILURE;
            }

            return Command::SUCCESS;
        }

        // Get total teams for progress reporting
        $totalTeams = $this->getTotalTeams();
        if (!$totalTeams) {
            $io->warning('No teams found.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d teams in total', $totalTeams));
        $totalProcessed = 0;
        $successCount = 0;
        $failureCount = 0;
        $batchNumber = 0;

        // Process teams in batches
        while ($offset < $totalTeams && $totalProcessed < $maxTeams) {
            $batchNumber++;

            // Check memory usage before loading a new batch
            $memoryUsage = memory_get_usage(true) / 1024 / 1024;
            $io->info(sprintf('Memory usage before batch %d: %.2f MB', $batchNumber, $memoryUsage));

            if ($memoryUsage > self::MEMORY_THRESHOLD) {
                $io->warning('Memory threshold reached, pausing for memory cleanup');
                $this->logger->warning('Memory threshold reached during team stats fetch', [
                    'memory_usage_mb' => $memoryUsage,
                    'threshold_mb' => self::MEMORY_THRESHOLD
                ]);

                // Force garbage collection
                gc_collect_cycles();
                sleep(2); // Give the system some time to free memory
                continue;
            }

            // Determine current batch size (respecting the max teams limit)
            $currentBatchSize = min($batchSize, $maxTeams - $totalProcessed);

            $io->section(sprintf('Processing batch %d: %d teams (offset: %d)',
                $batchNumber,
                $currentBatchSize,
                $offset
            ));

            try {
                // Load team data for batch
                $teamRows = $this->loadTeams($currentBatchSize, $offset);

                if (empty($teamRows)) {
                    $io->warning('No more teams found.');
                    break;
                }

                // Process the batch with parallel requests
                $batchResults = $this->processTeamsBatch($teamRows, $maxConcurrent, $io);

                $batchProcessed = $batchResults['total'];
                $batchSuccess = $batchResults['success'];
                $batchFailure = $batchResults['failure'];

                $totalProcessed += $batchProcessed;
                $successCount += $batchSuccess;
                $failureCount += $batchFailure;

                $offset += $currentBatchSize;

                $io->success(sprintf(
                    'Batch %d completed. Success: %d, Failures: %d. Total processed so far: %d/%d (%.1f%%)',
                    $batchNumber,
                    $batchSuccess,
                    $batchFailure,
                    $totalProcessed,
                    min($totalTeams, $maxTeams),
                    ($totalProcessed / min($totalTeams, $maxTeams)) * 100
                ));

                // Clear entity manager to free memory
                $this->entityManager->clear();

                // Force garbage collection
                gc_collect_cycles();

                // Sleep between batches to allow system to recover
                sleep(1);
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
            }
        }

        if ($failureCount > 0) {
            $io->warning(sprintf('%d teams failed to process', $failureCount));
        }

        $io->success(sprintf('Processing complete. Successfully processed %d/%d teams.',
            $successCount,
            $totalProcessed
        ));

        $this->logger->info('Team stats fetch operation completed', [
            'total_processed' => $totalProcessed,
            'successes' => $successCount,
            'failures' => $failureCount
        ]);

        return Command::SUCCESS;
    }

    /**
     * Load teams for batch processing
     */
    private function loadTeams(int $limit, int $offset): array
    {
        $conn = $this->entityManager->getConnection();
        return $conn->fetchAllAssociative(
            'SELECT id, name, pandascore_id, slug FROM team ORDER BY id ASC LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    /**
     * Process a batch of teams with parallel requests
     */
    private function processTeamsBatch(array $teamRows, int $maxConcurrent, SymfonyStyle $io): array
    {
        $pendingRequests = [];
        $activeRequests = [];
        $teamMap = [];
        $processed = 0;
        $success = 0;
        $failure = 0;
        $lastRequestTime = 0;
        $requestId = 0;

        // Prepare requests for all teams in the batch
        foreach ($teamRows as $teamData) {
            if (empty($teamData['pandascore_id'])) {
                $io->warning(sprintf('Team %s has no PandaScore ID, skipping', $teamData['name']));
                $processed++;
                $failure++;
                continue;
            }

            $pendingRequests[] = [
                'team_id' => $teamData['id'],
                'name' => $teamData['name'],
                'url' => sprintf(self::API_URL, $teamData['pandascore_id'])
            ];
        }

        // Process requests in parallel
        while (!empty($pendingRequests) || !empty($activeRequests)) {
            // Monitor memory usage
            if ($processed > 0 && $processed % 10 === 0) {
                $memoryUsage = memory_get_usage(true) / 1024 / 1024;
                $io->note(sprintf('Memory usage during processing: %.2f MB', $memoryUsage));

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
                $teamId = $request['team_id'];
                $teamName = $request['name'];

                try {
                    $currentRequestId = "request_" . $requestId++;

                    $io->text(sprintf('Sending request for team: %s (ID: %s)', $teamName, $teamId));

                    $response = $this->httpClient->request('GET', $request['url'], [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->pandascoreToken,
                            'Accept' => 'application/json',
                        ],
                        'timeout' => 30,
                        'user_data' => $currentRequestId,
                    ]);

                    $activeRequests[$currentRequestId] = $response;
                    $teamMap[$currentRequestId] = $teamId;
                    $lastRequestTime = microtime(true);
                } catch (\Exception $e) {
                    $this->logger->error('Error requesting team stats', [
                        'team_id' => $teamId,
                        'team_name' => $teamName,
                        'error' => $e->getMessage(),
                    ]);

                    $io->error(sprintf('Error requesting stats for team %s: %s', $teamName, $e->getMessage()));

                    $processed++;
                    $failure++;
                }
            }

            // Check for completed requests
            if (!empty($activeRequests)) {
                foreach ($this->httpClient->stream($activeRequests) as $response => $chunk) {
                    if ($chunk->isLast()) {
                        try {
                            // Get request ID and team ID
                            $id = $response->getInfo('user_data');
                            $teamId = $teamMap[$id];

                            // Clean up tracking
                            unset($activeRequests[$id]);
                            unset($teamMap[$id]);

                            $statusCode = $response->getStatusCode();
                            if ($statusCode !== 200) {
                                throw new \RuntimeException("API responded with status code $statusCode");
                            }

                            $data = json_decode($response->getContent(), true);

                            if (!is_array($data)) {
                                throw new \RuntimeException("Invalid JSON response");
                            }

                            // Load full team entity
                            $team = $this->teamRepository->find($teamId);
                            if (!$team) {
                                throw new \RuntimeException("Team not found in database");
                            }

                            // Process team stats data
                            $result = $this->pandaScoreService->processTeamStats($team, $data);

                            if ($result) {
                                $io->text(sprintf('<info>✓</info> Stats updated for team %s', $team->getName()));
                                $success++;
                            } else {
                                $io->text(sprintf('<comment>⚠</comment> Failed to update stats for team %s', $team->getName()));
                                $failure++;
                            }

                            $processed++;

                            // Clean up for memory savings
                            $data = null;
                            $team = null;
                        } catch (\Exception $e) {
                            $this->logger->error('Error processing team stats response', [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);

                            $io->error(sprintf('Error processing stats: %s', $e->getMessage()));

                            $processed++;
                            $failure++;
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
     * Process a single team
     */
    private function processTeam(Team $team, SymfonyStyle $io): bool
    {
        if (empty($team->getPandascoreId())) {
            $io->warning(sprintf('Team %s has no PandaScore ID, skipping', $team->getName()));
            return false;
        }

        try {
            $url = sprintf(self::API_URL, $team->getPandascoreId());

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

            $result = $this->pandaScoreService->processTeamStats($team, $data);

            if ($result) {
                $io->text(sprintf('<info>✓</info> Stats updated for team %s', $team->getName()));
                return true;
            } else {
                $io->text(sprintf('<comment>⚠</comment> Failed to update stats for team %s', $team->getName()));
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error processing team stats', [
                'team_id' => $team->getId(),
                'team_name' => $team->getName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $io->error(sprintf('Error fetching stats for team %s: %s', $team->getName(), $e->getMessage()));
            return false;
        }
    }

    /**
     * Get total number of teams in the database
     */
    private function getTotalTeams(): int
    {
        $conn = $this->entityManager->getConnection();
        return (int)$conn->fetchOne('SELECT COUNT(id) FROM team');
    }
}