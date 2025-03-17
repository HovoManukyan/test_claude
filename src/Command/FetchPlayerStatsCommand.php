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

#[AsCommand(
    name: 'app:fetch-player-stats',
    description: 'Fetch statistics for CS:GO players from PandaScore API'
)]
class FetchPlayerStatsCommand extends Command
{
    /**
     * Default batch size for processing players
     */
    private const BATCH_SIZE = 10;

    /**
     * Maximum concurrent requests to PandaScore API
     */
    private const MAX_CONCURRENT_REQUESTS = 3;

    /**
     * API rate limit in requests per second
     */
    private const RATE_LIMIT = 2;

    /**
     * Memory threshold in MB for pausing
     */
    private const MEMORY_THRESHOLD = 150;

    public function __construct(
        private readonly PandaScoreService $pandaScoreService,
        private readonly PlayerRepository $playerRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Number of players to process in one batch', self::BATCH_SIZE)
            ->addOption('offset', 'o', InputOption::VALUE_REQUIRED, 'Starting offset (skip first N players)', 0)
            ->addOption('max-players', 'm', InputOption::VALUE_REQUIRED, 'Maximum number of players to process', PHP_INT_MAX)
            ->addOption('id', 'i', InputOption::VALUE_REQUIRED, 'Process only a specific player ID', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Fetching statistics for CS:GO players from PandaScore API');

        // Set memory limit to handle large responses
        ini_set('memory_limit', '512M');

        $batchSize = (int)$input->getOption('batch-size');
        $offset = (int)$input->getOption('offset');
        $maxPlayers = (int)$input->getOption('max-players');
        $specificId = $input->getOption('id');

        $this->logger->info('Starting player stats fetch operation', [
            'batch_size' => $batchSize,
            'offset' => $offset,
            'max_players' => $maxPlayers,
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
            $this->processPlayer($player, $io);

            $io->success('Player stats updated successfully');
            return Command::SUCCESS;
        }

        // Process players in batches
        $totalPlayers = $this->getTotalPlayers();
        $totalProcessed = 0;
        $failureCount = 0;

        $io->info(sprintf('Found %d players in total', $totalPlayers));

        while ($offset < $totalPlayers && $totalProcessed < $maxPlayers) {
            // Check memory usage before loading a new batch
            $memoryUsage = memory_get_usage(true) / 1024 / 1024;
            $io->info(sprintf('Memory usage before batch: %.2f MB', $memoryUsage));

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
            $io->section(sprintf('Processing batch of %d players (offset: %d)', $currentBatchSize, $offset));

            // Load a batch of players
            $players = $this->playerRepository->findBy(
                [], // criteria
                ['id' => 'ASC'], // order by
                $currentBatchSize,
                $offset
            );

            if (empty($players)) {
                $io->warning('No more players found.');
                break;
            }

            $batchProcessed = 0;

            // Process each player in the batch
            foreach ($players as $player) {
                $io->text(sprintf('Processing player: %s (ID: %d)', $player->getName(), $player->getId()));

                try {
                    $success = $this->processPlayer($player, $io);

                    if ($success) {
                        $batchProcessed++;
                    } else {
                        $failureCount++;
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Error processing player', [
                        'player_id' => $player->getId(),
                        'player_name' => $player->getName(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    $io->error(sprintf('Error processing player %s: %s', $player->getName(), $e->getMessage()));
                    $failureCount++;
                }

                // Add a small delay to respect rate limits
                usleep((int)(1000000 / self::RATE_LIMIT));
            }

            $totalProcessed += $batchProcessed;
            $offset += $currentBatchSize;

            $io->success(sprintf('Batch completed. Processed %d/%d players in this batch. Total processed: %d/%d',
                $batchProcessed,
                count($players),
                $totalProcessed,
                min($totalPlayers, $maxPlayers)
            ));

            // Clear entity manager to free memory
            $this->entityManager->clear();

            // Force garbage collection
            gc_collect_cycles();

            // Sleep between batches to allow system to recover
            sleep(1);
        }

        if ($failureCount > 0) {
            $io->warning(sprintf('%d players failed to process', $failureCount));
        }

        $io->success(sprintf('Processing complete. Successfully processed %d/%d players.',
            $totalProcessed,
            min($totalPlayers, $maxPlayers)
        ));

        $this->logger->info('Player stats fetch operation completed', [
            'total_processed' => $totalProcessed,
            'failures' => $failureCount
        ]);

        return Command::SUCCESS;
    }

    /**
     * Process a single player to update their stats
     */
    private function processPlayer(Player $player, SymfonyStyle $io): bool
    {
        if (empty($player->getPandascoreId())) {
            $io->warning(sprintf('Player %s has no PandaScore ID, skipping', $player->getName()));
            return false;
        }

        try {
            $result = $this->pandaScoreService->fetchPlayerStats($player);

            if ($result) {
                $io->text(sprintf('Successfully updated stats for player %s', $player->getName()));
                return true;
            } else {
                $io->warning(sprintf('Failed to update stats for player %s', $player->getName()));
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error fetching player stats', [
                'player_id' => $player->getId(),
                'player_name' => $player->getName(),
                'error' => $e->getMessage()
            ]);

            $io->error(sprintf('Error fetching stats for player %s: %s', $player->getName(), $e->getMessage()));
            return false;
        }
    }

    /**
     * Get total number of players in the database
     */
    private function getTotalPlayers(): int
    {
        $conn = $this->entityManager->getConnection();
        return (int)$conn->fetchOne('SELECT COUNT(id) FROM player');
    }
}