<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Player;
use App\Entity\Team;
use DateTime;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'app:fetch-players',
    description: 'Fetch CS:GO players from PandaScore API',
)]
class FetchPlayersCommand extends Command
{
    private const API_URL = 'https://api.pandascore.co/csgo/players';
    private const PER_PAGE = 100;
    private const CONCURRENT_REQUESTS = 2;
    private const REQUEST_DELAY = 0.5;

    public function __construct(
        private readonly HttpClientInterface    $httpClient,
        private readonly EntityManagerInterface $entityManager
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '512M');

        $io = new SymfonyStyle($input, $output);
        $io->title('Fetching CS:GO players from PandaScore API');

        $page = 1;
        $hasMoreData = true;
        $totalSavedPlayers = 0;

        while ($hasMoreData) {
            $requests = [];
            for ($i = 0; $i < self::CONCURRENT_REQUESTS; $i++) {
                $pageNum = $page + $i;
                $url = sprintf('%s?page=%d&per_page=%d', self::API_URL, $pageNum, self::PER_PAGE);
                $requests[$pageNum] = [
                    'page' => $pageNum,
                    'request' => $this->httpClient->request('GET', $url, [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $_ENV['PANDASCORE_TOKEN'],
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
                        throw new \Exception('Invalid JSON response');
                    }

                    if (empty($data)) {
                        $emptyResponses++;
                        $io->warning(sprintf('Page %d is empty, stopping soon...', $pageNum));
                        continue;
                    }

                    $io->success(sprintf('Page %d fetched, %d players found', $pageNum, count($data)));

                    $players = [];

                    foreach ($data as $playerData) {
                        $playerId = (string)$playerData['id'];

                        if (isset($players[$playerId])) {
                            continue;
                        }
                        $player = new Player();
                        $player->setPandascoreId($playerId);
                        $player->setFirstName($playerData['first_name'] ?? null);
                        $player->setLastName($playerData['last_name'] ?? null);
                        $player->setName($playerData['name'] ?? 'Unknown');
                        $player->setSlug($playerData['slug'] ?? null);
                        $player->setNationality($playerData['nationality'] ?? null);
                        if (!empty($playerData['image_url'])) {
                            $player->setImage($playerData['image_url']);
                        }
                        $player->setBirthday(isset($playerData['birthday']) ? new DateTime($playerData['birthday']) : null);

                        if (isset($playerData['current_team']['id'])) {
                            $teamId = $playerData['current_team']['id'];
                            $team = $this->entityManager->getRepository(Team::class)
                                ->findOneBy(['pandascore_id' => $teamId]);
                            if($team){
                                $player->setCurrentTeam($team);
                                $player->addTeam($team);
                            }
                        }
                        $players[] = $player;
                    }
                    foreach ($players as $player) {
                        $this->entityManager->persist($player);
                    }
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                    $totalSavedPlayers += count($players);
                    $io->info(sprintf('Saved %d players from page %d', count($players), $pageNum));

                } catch (\Exception $e) {
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

        $io->success(sprintf('Fetching complete. Total players saved: %d', $totalSavedPlayers));
        return Command::SUCCESS;
    }
}
