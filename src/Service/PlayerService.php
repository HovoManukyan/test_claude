<?php

namespace App\Service;

use App\Entity\Player;
use App\Repository\PlayerRepository;
use App\Repository\SkinRepository;
use App\Repository\TeamRepository;
use App\Request\Player\PlayerUpdateRequest;
use App\Service\Http\HttpClientService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Filesystem\Filesystem;

class PlayerService
{

    private const PUBLIC_PATH = '/cdn/players';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SkinRepository         $skinRepository,
        private readonly PlayerRepository       $playerRepository,
        private readonly TeamRepository         $teamRepository,
        private readonly Filesystem             $filesystem,
        private readonly HttpClientService      $httpClientService,
        #[Autowire('%player_images_dir%')]
        private readonly string                 $playerImagesDir,
    )
    {
    }

    public function updatePlayer(Player $player, PlayerUpdateRequest $request): Player
    {
        $player->setFirstName($request->getFirstName());
        $player->setLastName($request->getLastName());
        $player->setBio($request->getBio());
        $player->setSocials($request->getSocials() ?? []);

        if ($request->getBirthday()) {
            $player->setBirthday(new \DateTimeImmutable($request->getBirthday()));
        }

        $crosshair = $request->getCrosshair();
        if ($crosshair === null) {
            $player->setCrosshair(null);
        } else {
            $player->setCrosshair([
                'crosshairId' => $crosshair->getCrosshairId(),
                'style' => $crosshair->getStyle(),
                'size' => $crosshair->getSize(),
                'thickness' => $crosshair->getThickness(),
                'tShape' => $crosshair->getTShape(),
                'dot' => $crosshair->getDot(),
                'gap' => $crosshair->getGap(),
                'alpha' => $crosshair->getAlpha(),
                'color' => $crosshair->getColor(),
                'colorR' => $crosshair->getColorR(),
                'colorG' => $crosshair->getColorG(),
                'colorB' => $crosshair->getColorB(),
            ]);
        }

        if ($request->getSkins()) {
            $player->getSkins()->clear();
            foreach ($request->getSkins() as $skinId) {
                $skin = $this->skinRepository->find($skinId);
                if ($skin) {
                    $player->addSkin($skin);
                }
            }
        }

        $this->entityManager->flush();
        return $player;
    }

    /**
     * Получаем данные из запроса в пандаскор, проверяем и добавляем их в базу пачкой
     *
     * @param array $players
     * @return void
     * @throws Exception
     */
    public function syncBatchFromApi(array $players): void
    {
        $ids = array_column($players, 'id');
        $existingPlayers = $this->playerRepository->findBy(['pandascoreId' => $ids]);

        $playerMap = [];
        foreach ($existingPlayers as $player) {
            $playerMap[$player->getPandascoreId()] = $player;
        }

        foreach ($players as $data) {
            $id = $data['id'];

            $player = $playerMap[$id] ?? new Player();
            $player->setPandascoreId($id);
            $player->setFirstName($data['first_name'] ?? null);
            $player->setLastName($data['last_name'] ?? null);
            $player->setName($data['name'] ?? 'Unknown');
            $player->setSlug($data['slug'] ?? null);
            $player->setNationality($data['nationality'] ?? null);

            if (!empty($data['image_url'])) {
                $player->setImage($data['image_url']);
            }

            $player->setBirthday(isset($data['birthday']) ? new DateTimeImmutable($data['birthday']) : null);

            if (isset($data['current_team']['id'])) {
                $teamId = $data['current_team']['id'];
                $team = $this->teamRepository->findOneBy(['pandascoreId' => $teamId]);
                if ($team) {
                    $player->setCurrentTeam($team);
                    $player->addTeam($team);
                }
            }

            $this->entityManager->persist($player);
        }

        $this->entityManager->flush();
    }

    /**
     * @param Player $player
     * @return bool
     */
    public function downloadPlayerImage(Player $player): bool
    {
        $imageUrl = $player->getImage();
        if (!$imageUrl) {
            return false;
        }

        $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $filename = sprintf('%s.%s', $player->getPandascoreId(), $extension);
        $targetPath = $this->playerImagesDir . '/' . $filename;

        if ($this->filesystem->exists($targetPath)) {
            $player->setImage(self::PUBLIC_PATH . $filename);
            $this->entityManager->persist($player);
            return true;
        }

        $content = $this->httpClientService->downloadFile($imageUrl);
        $this->filesystem->dumpFile($targetPath, $content);

        $player->setImage(self::PUBLIC_PATH . $filename);
        $this->entityManager->persist($player);
        return true;
    }

    /**
     * @param Player $player
     * @param array $data
     * @return void
     */
    public function updatePlayerStats(Player $player, array $data): void
    {
        echo 'updating player stats for player '. $player->getSlug() . ' | ';
        if (!empty($data['current_team']['id'])) {
            $currentTeamId = (string) $data['current_team']['id'];
            $currentTeam = $this->teamRepository->findOneBy(['pandascoreId' => $currentTeamId]);

            if ($currentTeam && $player->getCurrentTeam()?->getPandascoreId() !== $currentTeamId) {
                $player->setCurrentTeam($currentTeam);
            }
        }

        if (!empty($data['last_games']) && is_array($data['last_games'])) {
            $player->setLastGames($data['last_games']);
        }

        if (!empty($data['stats']) && is_array($data['stats'])) {
            $player->setStats($data['stats']);
        }

        if (!empty($data['teams']) && is_array($data['teams'])) {
            foreach ($data['teams'] as $teamData) {
                if (!isset($teamData['id'])) {
                    continue;
                }

                $team = $this->teamRepository->findOneBy(['pandascoreId' => (string)$teamData['id']]);
                if ($team && !$player->getTeams()->contains($team)) {
                    $player->addTeam($team);
                }
            }
        }

        $this->entityManager->persist($player);
    }

}
