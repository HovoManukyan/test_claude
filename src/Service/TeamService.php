<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Team;
use App\Repository\TeamRepository;
use App\Request\Team\TeamUpdateRequest;
use App\Service\Http\HttpClientService;
use App\Value\PaginatedResult;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class TeamService
{
    private const PUBLIC_PATH = '/cdn/teams';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TeamRepository         $teamRepository,
        private readonly HttpClientService      $httpClientService,
        private readonly Filesystem             $filesystem,
        #[Autowire('%team_images_dir%')]
        private readonly string                 $teamImagesDir,
    )
    {
    }

    /**
     * Update a team
     *
     * @param Team $team
     * @param TeamUpdateRequest $request
     * @return Team|null Updated team or null if not found
     */
    public function updateTeam(Team $team, TeamUpdateRequest $request): ?Team
    {
        $team->setBio($request->getBio())
            ->setSocials($request->getSocials());

        $this->entityManager->flush();
        return $team;
    }

    public function syncBatchFromApi(array $teams): void
    {
        if (empty($teams)) {
            return;
        }

        $ids = array_column($teams, 'id');
        $existing = $this->teamRepository->findBy(['pandascoreId' => $ids]);

        $existingMap = [];
        foreach ($existing as $team) {
            $existingMap[$team->getPandascoreId()] = $team;
        }

        foreach ($teams as $data) {
            $id = (string) $data['id'];

            $team = $existingMap[$id] ?? new Team();
            $team->setPandascoreId($id);
            $team->setName($data['name'] ?? 'Unknown');
            $team->setSlug($data['slug'] ?? null);
            $team->setAcronym($data['acronym'] ?? null);
            $team->setLocation($data['location'] ?? null);
            $team->setImage($data['image_url'] ?? null);

            if (!isset($existingMap[$id])) {
                $this->entityManager->persist($team);
                $existingMap[$id] = $team;
            }
        }

    }

    public function downloadTeamImage(Team $team): bool
    {
        $imageUrl = $team->getImage();
        if (!$imageUrl) {
            return false;
        }

        $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $filename = sprintf('%s.%s', $team->getPandascoreId(), $extension);
        $targetPath = $this->teamImagesDir . '/' . $filename;

        if ($this->filesystem->exists($targetPath)) {
            $team->setImage(self::PUBLIC_PATH . $filename);
            $this->entityManager->persist($team);
            return true;
        }

        $content = $this->httpClientService->downloadFile($imageUrl);
        $this->filesystem->dumpFile($targetPath, $content);

        $team->setImage(self::PUBLIC_PATH . $filename);
        $this->entityManager->persist($team);
        return true;
    }
}