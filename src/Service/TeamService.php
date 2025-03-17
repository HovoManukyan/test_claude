<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Team;
use App\Repository\TeamRepository;
use App\Value\PaginatedResult;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class TeamService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TeamRepository $teamRepository,
        private readonly LoggerInterface $logger,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Get teams with pagination and filters for admin
     *
     * @param int $page Page number
     * @param int $limit Results per page
     * @param string|null $location Filter by location
     * @param string|null $name Filter by name
     * @return PaginatedResult Teams with pagination metadata
     */
    public function getAllTeamsForAdmin(int $page, int $limit, ?string $location = null, ?string $name = null): PaginatedResult
    {
        // Convert location to array if provided
        $locales = $location ? [$location] : null;

        // Get paginated teams
        $result = $this->teamRepository->findPaginated($page, $limit, $name, $locales);

        // Return as value object
        return PaginatedResult::fromRepositoryResult($result, $page, $limit);
    }

    /**
     * Get all teams with pagination and filters for public API
     *
     * @param int $page Page number
     * @param int $limit Results per page
     * @param string|null $name Filter by name
     * @param array|null $locales Filter by locations
     * @return PaginatedResult Teams with pagination metadata
     */
    public function getAllTeams(int $page, int $limit, ?string $name = null, ?array $locales = null): PaginatedResult
    {
        // Get paginated teams
        $result = $this->teamRepository->findPaginated($page, $limit, $name, $locales);

        // Return as value object
        return PaginatedResult::fromRepositoryResult($result, $page, $limit);
    }

    /**
     * Get a team by ID
     *
     * @param int $id Team ID
     * @return Team|null Team entity or null if not found
     */
    public function getTeamById(int $id): ?Team
    {
        return $this->teamRepository->find($id);
    }

    /**
     * Get a team by slug
     *
     * @param string $slug Team slug
     * @return Team|null Team entity or null if not found
     */
    public function getTeamBySlug(string $slug): ?Team
    {
        return $this->teamRepository->findOneBySlug($slug);
    }

    /**
     * Get teams by their slugs
     *
     * @param array $slugs Team slugs
     * @return Team[] Team entities
     */
    public function getTeamsBySlug(array $slugs): array
    {
        return $this->teamRepository->findBySlug($slugs);
    }

    /**
     * Get random teams
     *
     * @param int $limit Number of teams to return
     * @return Team[] Team entities
     */
    public function getRandomTeams(int $limit = 12): array
    {
        return $this->teamRepository->findRandom($limit);
    }

    /**
     * Update a team
     *
     * @param int $teamId Team ID
     * @param string|null $bio Team biography
     * @param array|null $socials Social media links
     * @return Team|null Updated team or null if not found
     * @throws \InvalidArgumentException|\Exception If social media links are invalid
     */
    public function updateTeam(int $teamId, ?string $bio, ?array $socials): ?Team
    {
        $team = $this->teamRepository->find($teamId);

        if (!$team) {
            return null;
        }

        // Update biography if provided
        if ($bio !== null) {
            $team->setBio($bio);
        }

        // Update social media links if provided
        if ($socials !== null) {
            $errors = $this->validateSocials($socials);

            if (!empty($errors)) {
                throw new \InvalidArgumentException(implode(', ', $errors));
            }

            $team->setSocials($socials);
        }

        // Save changes
        try {
            $this->entityManager->flush();

            return $team;
        } catch (\Exception $e) {
            $this->logger->error('Error updating team', [
                'teamId' => $teamId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Validate social media links
     *
     * @param array $socials Social media links
     * @return array Validation errors
     */
    private function validateSocials(array $socials): array
    {
        $constraint = new Assert\Collection([
            'fields' => [
                'vk' => new Assert\Optional([new Assert\Url()]),
                'tg' => new Assert\Optional([new Assert\Url()]),
                'twitter' => new Assert\Optional([new Assert\Url()]),
                'instagram' => new Assert\Optional([new Assert\Url()]),
                'facebook' => new Assert\Optional([new Assert\Url()]),
                'youtube' => new Assert\Optional([new Assert\Url()]),
                'twitch' => new Assert\Optional([new Assert\Url()]),
                'discord' => new Assert\Optional([new Assert\Url()]),
            ],
            'allowExtraFields' => true,
        ]);

        $violations = $this->validator->validate($socials, $constraint);
        $errors = [];

        foreach ($violations as $violation) {
            $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
        }

        return $errors;
    }
}