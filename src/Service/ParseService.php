<?php

namespace App\Service;

use App\Entity\Player;
use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ParseService
{
    const PANDASCORE_URL = "https://api.pandascore.co/csgo";
    private HttpClientInterface $client;
    private EntityManagerInterface $entityManager;

    public function __construct(HttpClientInterface $client, EntityManagerInterface $entityManager)
    {
        $this->client = $client;
        $this->entityManager = $entityManager;
    }

    public function parsePlayers($page, $limit): int|bool
    {
        try {
            $response = $this->client->request(
                'GET',
                self::PANDASCORE_URL . '/players',
                [
                    'headers' => [
                        'accept' => 'application/json',
                        'Authorization' => 'Bearer ' . $_ENV['PANDASCORE_TOKEN'],
                    ],
                    'query' => [
                        'page' => $page,
                        'per_page' => $limit,
                    ],
                ]
            );
            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException('API responded with an error: ' . $response->getStatusCode());
            }
            $content = $response->getContent();
            $players = json_decode($content, true);
            foreach ($players as $playerData) {
                $player = $this->entityManager->getRepository(Player::class)->findOneBy(['pandascore_id' => $playerData['id']]);

                if (!$player) {
                    $player = new Player();
                    $player->setPandascoreId($playerData['id']);
                }

                $player->setFirstName($playerData['first_name']);
                $player->setLastName($playerData['last_name']);
                $player->setName($playerData['name']);
                $player->setSlug($playerData['slug']);

                if (!empty($playerData['image_url'])) {
                    $imagePath = $this->downloadAndSaveImage($playerData['image_url'], $playerData['id'], 'players');
                    $player->setImage($imagePath);
                }
                $player->setNationality($playerData['nationality'] ?? null);
                $player->setBirthday(isset($playerData['birthday']) ? new \DateTime($playerData['birthday']) : null);

                if (isset($playerData['current_team'])) {
                    $teamData = $playerData['current_team'];
                    $team = $this->checkTeamAndAdd($teamData);
                    $player->setCurrentTeam($team);
                    $player->addTeam($team);
                }
                $this->entityManager->persist($player);
            }

            $this->entityManager->flush();
            return count($players);
        } catch (Exception|TransportExceptionInterface|ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface $exception) {
            echo $exception->getMessage();
            return false;
        }
    }

    public function parseTeams($page, $limit): int|bool
    {
        try {
            $response = $this->client->request(
                'GET',
                self::PANDASCORE_URL . '/teams',
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer ' . $_ENV['PANDASCORE_TOKEN'],
                    ],
                    'query' => [
                        'page' => $page,
                        'per_page' => $limit,
                    ]
                ]
            );

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException('API responded with an error: ' . $response->getStatusCode());
            }

            $teamsData = $response->toArray();

            foreach ($teamsData as $teamData) {
                $this->checkTeamAndAdd($teamData);
            }

            $this->entityManager->flush();
            return count($teamsData);
        } catch (TransportExceptionInterface $exception) {
            echo $exception->getMessage();
            return false;
        }
    }

    public function parsePlayerStatistics($page, $limit)
    {
        $offset = $page - 1 * $limit;
        $players = $this->entityManager->getRepository(Player::class)->findBy([], null, $limit, $offset);

        foreach ($players as $player) {
            $response = $this->client->request(
                'GET',
                self::PANDASCORE_URL . '/players/'.$player->getSlug().'/stats',
                [
                    'headers' => [
                        'accept' => 'application/json',
                        'Authorization' => 'Bearer ' . $_ENV['PANDASCORE_TOKEN'],
                    ],
                    'query' => [
                        'page' => $page,
                        'per_page' => $limit,
                    ],
                ]
            );
            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException('API responded with an error: ' . $response->getStatusCode());
            }
            $statistics = $response->toArray();



        }



        /** При парсе статистики вместо страницы $page служит как id последнего игрока.
         * При достижении последнего id игрока, снова становится 0 и круг начинается с начала */
        return $limit;
    }

    /**
     * @param mixed $teamData
     * @return Team
     */
    public function addTeam(mixed $teamData): Team
    {
        $team = new Team();
        $team->setPandascoreId($teamData['id']);
        $team->setName($teamData['name']);
        $team->setSlug($teamData['slug']);
        $team->setImage($teamData['image_url']);
        $team->setLocation($teamData['location'] ?? null);
        $this->entityManager->persist($team);
        $this->entityManager->flush();
        return $team;
    }

    private function checkTeamAndAdd(mixed $teamData)
    {
        $team = $this->entityManager->getRepository(Team::class)->findOneBy(['pandascore_id' => $teamData['id']]);
        if (!$team) {
            $team = new Team();
            $team->setPandascoreId($teamData['id']);
            $team->setName($teamData['name']);
            $team->setSlug($teamData['slug']);
            if (!empty($teamData['image_url'])) {
                $imagePath = $this->downloadAndSaveImage($teamData['image_url'], $teamData['id'], 'teams');
                $team->setImage($imagePath);
            };
            $team->setLocation($teamData['location'] ?? '');
            $this->entityManager->persist($team);
            $this->entityManager->flush();
        }
        return $team;
    }

    private function downloadAndSaveImage(string $url, int $id, string $type = 'general'): string
    {
        $uploadDir = __DIR__ . "/../../public/cdn/{$type}";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $imagePath = "{$uploadDir}/{$id}.jpg";

        // Скачивание изображения
        $imageContent = file_get_contents($url);
        if ($imageContent === false) {
            throw new \Exception("Unable to download image from URL: $url");
        }

        // Сохраняем файл
        file_put_contents($imagePath, $imageContent);

        // Возвращаем относительный путь для базы данных
        return "/cdn/{$type}/{$id}.jpg";
    }

}