<?php

namespace App\Repository;

use App\Entity\Player;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * @method Player|null find($id, $lockMode = null, $lockVersion = null)
 * @method Player|null findOneBy(array $criteria, array $orderBy = null)
 * @method Player[]    findAll()
 * @method Player[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PlayerRepository extends BaseRepository
{
    private SluggerInterface $slugger;

    public function __construct(ManagerRegistry $registry, SluggerInterface $slugger)
    {
        parent::__construct($registry, Player::class);
        $this->slugger = $slugger;
    }

    /**
     * Find players with pagination and filters for public API
     *
     * @param int $page Page number
     * @param int $limit Results per page
     * @param bool|null $hasCrosshair Filter by crosshair presence
     * @param array|null $teamSlugs Filter by team slugs
     * @param string|null $name Filter by name
     * @return array Result with data, total and pages
     */
    public function findPaginated(
        int $page,
        int $limit,
        ?bool $hasCrosshair = null,
        ?array $teamSlugs = null,
        ?string $name = null
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.currentTeam', 'ct');

        // Apply crosshair filter
        if ($hasCrosshair !== null) {
            $qb->andWhere($hasCrosshair ? 'p.crosshair IS NOT NULL' : 'p.crosshair IS NULL');
        }

        // Apply team filter
        if (!empty($teamSlugs)) {
            $qb->join('p.teams', 't')
                ->andWhere('t.slug IN (:teamSlugs)')
                ->setParameter('teamSlugs', $teamSlugs);
        }

        // Apply name filter
        if ($name !== null && $name !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('LOWER(p.firstName)', ':name'),
                    $qb->expr()->like('LOWER(p.lastName)', ':name'),
                    $qb->expr()->like('LOWER(p.name)', ':name')
                )
            )
                ->setParameter('name', '%' . strtolower(trim($name)) . '%');
        }

        // Add default ordering
        $qb->orderBy('p.name', 'ASC');

        // Use base paginator
        return $this->paginate($qb, $page, $limit);
    }

    /**
     * Find players with pagination and filters for admin
     *
     * @param int $page Page number
     * @param int $limit Results per page
     * @param array $filters Additional filters
     * @return array Result with data, total and pages
     */
    public function findPaginatedForAdmin(int $page, int $limit, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.currentTeam', 'ct')
            ->leftJoin('p.skins', 's');

        // Apply name filter
        if (!empty($filters['name'])) {
            $qb->andWhere('LOWER(p.name) LIKE LOWER(:name)')
                ->setParameter('name', '%' . strtolower(trim($filters['name'])) . '%');
        }

        // Apply team filter
        if (!empty($filters['team'])) {
            $qb->join('p.teams', 't')
                ->andWhere('LOWER(t.name) LIKE LOWER(:team)')
                ->setParameter('team', '%' . strtolower(trim($filters['team'])) . '%');
        }

        // Apply country filter
        if (!empty($filters['country'])) {
            $qb->andWhere('p.nationality = :country')
                ->setParameter('country', $filters['country']);
        }

        // Apply crosshair filter
        if (isset($filters['hasCrosshair'])) {
            $qb->andWhere($filters['hasCrosshair'] ? 'p.crosshair IS NOT NULL' : 'p.crosshair IS NULL');
        }

        // Add default ordering
        $qb->orderBy('p.name', 'ASC');

        // Use base paginator
        return $this->paginate($qb, $page, $limit);
    }

    /**
     * Find a player by slug with all relations
     *
     * @param string $slug Player slug
     * @return Player|null Player entity or null if not found
     */
    public function findOneBySlugWithRelations(string $slug): ?Player
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.currentTeam', 'ct')
            ->leftJoin('p.teams', 't')
            ->leftJoin('p.skins', 's')
            ->leftJoin('p.games', 'g')
            ->leftJoin('p.playerTournaments', 'pt')
            ->where('p.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find players by team ID
     *
     * @param int $teamId Team ID
     * @return array Player entities
     */
    public function findByTeam(int $teamId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.currentTeam = :teamId')
            ->setParameter('teamId', $teamId)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Generate a unique slug for a player
     *
     * @param string $name Player name
     * @param int|null $excludeId Exclude player ID from uniqueness check
     * @return string Unique slug
     */
    public function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug = $this->slugger->slug(strtolower($name))->toString();

        $qb = $this->createQueryBuilder('p')
            ->select('p.slug')
            ->where('p.slug = :slug OR p.slug LIKE :slug_pattern')
            ->setParameter('slug', $slug)
            ->setParameter('slug_pattern', $slug . '-%');

        if ($excludeId !== null) {
            $qb->andWhere('p.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        $existingSlugs = $qb->getQuery()->getScalarResult();

        // If slug is unique, return it
        if (empty($existingSlugs)) {
            return $slug;
        }

        // Extract existing numbers
        $numbers = [];
        foreach ($existingSlugs as $row) {
            $existingSlug = $row['slug'];
            if ($existingSlug === $slug) {
                $numbers[] = 1;
            } elseif (preg_match('/' . preg_quote($slug, '/') . '-(\d+)$/', $existingSlug, $matches)) {
                $numbers[] = (int) $matches[1];
            }
        }

        // Find the next available number
        $nextNumber = empty($numbers) ? 1 : max($numbers) + 1;

        return $slug . '-' . $nextNumber;
    }
}