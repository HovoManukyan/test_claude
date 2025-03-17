<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Team;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'app:fetch-teams',
    description: 'Fetch CS:GO teams from PandaScore API',
)]
class FetchTeamsCommand extends Command
{
    private const API_URL = 'https://api.pandascore.co/csgo/teams';
    private const PER_PAGE = 100;
    private const CONCURRENT_REQUESTS = 2;
    private const REQUEST_DELAY = 0.5;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Fetching CS:GO teams from PandaScore API');

        $page = 1;
        $hasMoreData = true;
        $totalSavedTeams = 0;

        while ($hasMoreData) {
            $requests = [];

            for ($i = 0; $i < self::CONCURRENT_REQUESTS; $i++) {
                $pageNum = $page + $i;
                $url = sprintf('%s?page=%d&per_page=%d', self::API_URL, $pageNum, self::PER_PAGE);

                $requests[$pageNum] = [
                    'page' => $pageNum,
                    'request' => $this->httpClient->request('GET', $url, [
                        'headers' => [
                            'Authorization' => 'Bearer '.$_ENV['PANDASCORE_TOKEN'],
                            'Accept' => 'application/json',
                        ],
                    ]),
                ];
            }

            $emptyResponses = 0;

            foreach ($requests as $requestData) {
                try {
                    $pageNum = $requestData['page'];
                    $response = $requestData['request'];
                    $data = json_decode($response->getContent(), true);

                    if (!is_array($data)) {
                        throw new Exception('Invalid JSON response');
                    }

                    if (empty($data)) {
                        $emptyResponses++;
                        $io->warning(sprintf('Page %d is empty, stopping soon...', $pageNum));
                        continue;
                    }

                    $io->success(sprintf('Page %d fetched, %d teams found', $pageNum, count($data)));

                    $teams = [];
                    foreach ($data as $teamData) {
                        $team = new Team();
                        $team->setPandascoreId((string)$teamData['id']);
                        $team->setName($teamData['name'] ?? 'Unknown');
                        $team->setSlug($teamData['slug'] ?? null);
                        $team->setAcronym($teamData['acronym'] ?? null);
                        $team->setLocation($teamData['location'] ?? null);
                        $team->setImage($teamData['image_url'] ?? null);
                        $teams[] = $team;
                    }

                    foreach ($teams as $team) {
                        $this->entityManager->persist($team);
                    }

                    $this->entityManager->flush();
                    $this->entityManager->clear();

                    $totalSavedTeams += count($teams);
                    $io->info(sprintf('Saved %d teams from page %d', count($teams), $pageNum));

                } catch (Exception $e) {
                    $io->error(sprintf('Error fetching page %d: %s', $pageNum, $e->getMessage()));
                }
            }

            if ($emptyResponses === self::CONCURRENT_REQUESTS) {
                $io->warning('No more data found, stopping.');
                $hasMoreData = false;
            }

            $page += self::CONCURRENT_REQUESTS;
            usleep((int)(self::REQUEST_DELAY * 1_000_000));
        }

        $io->success(sprintf('Fetching complete. Total teams saved: %d', $totalSavedTeams));
        return Command::SUCCESS;
    }
}
