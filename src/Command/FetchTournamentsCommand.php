<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\PandaScoreService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:fetch-tournaments',
    description: 'Fetch CS:GO tournaments from PandaScore API with parallel processing',
)]
class FetchTournamentsCommand extends Command
{
    /**
     * API endpoint for tournaments
     */
    private const API_URL = 'https://api.pandascore.co/csgo/tournaments/past';

    /**
     * Default number of items per page
     */
    private const PER_PAGE = 100;

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
    private const MEMORY_THRESHOLD = 800;

    public function __construct(
        private readonly PandaScoreService $pandaScoreService,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $pandascoreToken,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('start-page', 'p', InputOption::VALUE_REQUIRED, 'Starting page number', 1)
            ->addOption('per-page', 'l', InputOption::VALUE_REQUIRED, 'Results per page', self::PER_PAGE)
            ->addOption('max-pages', 'm', InputOption::VALUE_REQUIRED, 'Maximum number of pages to fetch', 100)
            ->addOption('concurrent', 'c', InputOption::VALUE_REQUIRED, 'Number of concurrent requests', self::MAX_CONCURRENT_REQUESTS);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Fetching CS:GO tournaments from PandaScore API with parallel processing');

        // Set memory limit to handle parallel processing
        ini_set('memory_limit', '1024M');

        $startPage = (int)$input->getOption('start-page');
        $perPage = (int)$input->getOption('per-page');
        $maxPages = (int)$input->getOption('max-pages');
        $maxConcurrent = (int)$input->getOption('concurrent');

        $this->logger->info('Starting tournament fetch operation', [
            'start_page' => $startPage,
            'per_page' => $perPage,
            'max_pages' => $maxPages,
            'max_concurrent' => $maxConcurrent
        ]);

        $pendingPages = [];
        for ($page = $startPage; $page < $startPage + $maxPages; $page++) {
            $pendingPages[] = $page;
        }

        $activeRequests = [];
        $pageToRequestMap = [];
        $requestId = 0;
        $totalSavedTournaments = 0;
        $processedPages = 0;
        $emptyPagesCount = 0;
        $lastRequestTime = microtime(true);
        $processedTournamentIds = []; // To avoid duplicates

        // Process requests in parallel
        while (!empty($pendingPages) || !empty($activeRequests)) {
            // Monitor memory usage and report
            if ($processedPages > 0 && $processedPages % 5 === 0) {
                $memoryUsage = memory_get_usage(true) / 1024 / 1024;
                $io->note(sprintf('Memory usage: %.2f MB', $memoryUsage));

                if ($memoryUsage > self::MEMORY_THRESHOLD) {
                    $io->warning('High memory usage detected, forcing garbage collection');
                    gc_collect_cycles();
                    sleep(1); // Give system time to free memory
                }
            }

            // Add new requests up to the concurrent limit
            $now = microtime(true);
            while (!empty($pendingPages) && count($activeRequests) < $maxConcurrent) {
                // Respect rate limit
                if ($now - $lastRequestTime < (1 / self::RATE_LIMIT)) {
                    $sleepTime = (1 / self::RATE_LIMIT) - ($now - $lastRequestTime);
                    usleep((int)($sleepTime * 1_000_000));
                    $now = microtime(true);
                }

                $page = array_shift($pendingPages);
                $currentRequestId = "request_" . $requestId++;

                try {
                    $url = sprintf('%s?page=%d&per_page=%d', self::API_URL, $page, $perPage);
                    $io->text(sprintf('Sending request for page %d...', $page));

                    $response = $this->httpClient->request('GET', $url, [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->pandascoreToken,
                            'Accept' => 'application/json',
                        ],
                        'user_data' => $currentRequestId,
                    ]);

                    $activeRequests[$currentRequestId] = $response;
                    $pageToRequestMap[$currentRequestId] = $page;
                    $lastRequestTime = microtime(true);
                } catch (\Exception $e) {
                    $this->logger->error('Error requesting tournaments page', [
                        'page' => $page,
                        'error' => $e->getMessage(),
                    ]);
                    $io->error(sprintf('Error requesting page %d: %s', $page, $e->getMessage()));
                }
            }

            // Check for completed requests
            if (!empty($activeRequests)) {
                foreach ($this->httpClient->stream($activeRequests) as $response => $chunk) {
                    if ($chunk->isLast()) {
                        try {
                            // Get the request ID and page
                            $id = $response->getInfo('user_data');
                            $page = $pageToRequestMap[$id];

                            // Remove from active tracking
                            unset($activeRequests[$id]);
                            unset($pageToRequestMap[$id]);

                            $statusCode = $response->getStatusCode();
                            if ($statusCode !== 200) {
                                throw new \RuntimeException("API responded with status code $statusCode");
                            }

                            $data = json_decode($response->getContent(), true);

                            if (!is_array($data)) {
                                throw new \RuntimeException("Invalid JSON response");
                            }

                            if (empty($data)) {
                                $io->warning(sprintf('Page %d is empty, no tournaments found', $page));
                                $this->logger->info('Empty tournaments page', ['page' => $page]);
                                $emptyPagesCount++;

                                // If we've seen 3 consecutive empty pages, assume we're done
                                if ($emptyPagesCount >= 3) {
                                    $io->warning('Multiple empty pages found, stopping future requests');
                                    $pendingPages = []; // Clear pending pages
                                }
                            } else {
                                $emptyPagesCount = 0; // Reset empty pages counter
                                $savedCount = 0;

                                // Process each tournament through PandaScoreService
                                foreach ($data as $tournamentData) {
                                    // Skip already processed tournaments
                                    if (isset($tournamentData['id']) && in_array($tournamentData['id'], $processedTournamentIds)) {
                                        $io->text(sprintf('Skipping duplicate tournament: %s (ID: %s)',
                                            $tournamentData['name'] ?? 'Unknown',
                                            $tournamentData['id']
                                        ));
                                        continue;
                                    }

                                    if ($this->pandaScoreService->processTournamentData($tournamentData)) {
                                        $savedCount++;

                                        // Add to processed IDs list to avoid duplicates
                                        if (isset($tournamentData['id'])) {
                                            $processedTournamentIds[] = $tournamentData['id'];
                                        }
                                    }
                                }

                                // Flush changes after processing all tournaments on the page
                                $this->pandaScoreService->flushChanges();

                                $totalSavedTournaments += $savedCount;
                                $io->success(sprintf('Processed page %d: Saved %d tournaments', $page, $savedCount));
                                $this->logger->info('Saved tournaments from page', [
                                    'page' => $page,
                                    'count' => $savedCount,
                                    'total_so_far' => $totalSavedTournaments
                                ]);
                            }

                            $processedPages++;
                        } catch (\Exception $e) {
                            $io->error(sprintf('Error processing page response: %s', $e->getMessage()));
                            $this->logger->error('Error processing tournament data', [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    }
                }
            } else if (empty($pendingPages)) {
                // No active requests and no pending pages - we're done
                break;
            } else {
                // No active requests but pending pages remain - small pause
                usleep(50000); // 50ms
            }

            // Force garbage collection periodically
            if ($processedPages % 10 === 0) {
                gc_collect_cycles();
            }
        }

        $io->success(sprintf(
            'Fetching complete. Processed %d pages, saved %d tournaments.',
            $processedPages,
            $totalSavedTournaments
        ));

        $this->logger->info('Tournament fetch operation completed successfully', [
            'total_tournaments_saved' => $totalSavedTournaments,
            'pages_processed' => $processedPages
        ]);

        return Command::SUCCESS;
    }
}