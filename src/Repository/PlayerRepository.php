<?php

namespace App\Repository;

use App\Entity\Player;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;
use Psr\Log\LoggerInterface;

/**
 * @method Player|null find($id, $lockMode = null, $lockVersion = null)
 * @method Player|null findOneBy(array $criteria, array $orderBy = null)
 * @method Player[]    findAll()
 * @method Player[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PlayerRepository extends BaseRepository
{
    use DoctrineResultCache;

    /**
     * @var bool Is debug mode enabled
     */
    private bool $debugMode;

    /**
     * @var LoggerInterface Logger for cache operations
     */
    private LoggerInterface $logger;

    public function __construct(
        ManagerRegistry $registry,
        SluggerInterface $slugger,
        LoggerInterface $logger,
        #[Autowire('%kernel.debug%')]
        bool $debugMode
    ) {
        parent::__construct($registry, Player::class);
        $this->slugger = $slugger;
        $this->logger = $logger;
        $this->debugMode = $debugMode;
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

        // Create cache key for this query
        $cacheParams = [
            'hasCrosshair' => $hasCrosshair,
            'teamSlugs' => $teamSlugs,
            'name' => $name,
        ];
        $cacheKey = $this->createQueryCacheKey('player_list', $cacheParams);

        // Clone query builder to count total results
        $countQb = clone $qb;
        $countQb->select('COUNT(DISTINCT p.id)');
        $countQb->resetDQLPart('orderBy');

        // Execute count query with cache
        $totalQuery = $this->createCachableQuery($countQb, $cacheKey . '_count', 600);
        $total = (int)$totalQuery->getSingleScalarResult();

        // Calculate pages
        $pages = $limit > 0 ? ceil($total / $limit) : 1;

        // Apply pagination to the original query
        $firstResult = ($page - 1) * $limit;
        $qb->setFirstResult($firstResult)
            ->setMaxResults($limit);

        // Execute main query with cache
        $query = $this->createCachableQuery($qb, $cacheKey . '_' . $page, 600);
        $results = $query->getResult();

        return [
            'data' => $results,
            'total' => $total,
            'pages' => $pages,
        ];
    }

    /**
     * Find a player by slug with all relations
     *
     * @param string $slug Player slug
     * @return Player|null Player entity or null if not found
     */
    public function findOneBySlugWithRelations(string $slug): ?Player
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.currentTeam', 'ct')
            ->leftJoin('p.teams', 't')
            ->leftJoin('p.skins', 's')
            ->leftJoin('p.games', 'g')
            ->leftJoin('p.playerTournaments', 'pt')
            ->where('p.slug = :slug')
            ->setParameter('slug', $slug);

        // Create a cache key for this specific player
        $cacheKey = 'player_by_slug_' . $slug;

        // Create query with result cache
        $query = $this->createCachableQuery($qb, $cacheKey, 1800);

        return $query->getOneOrNullResult();
    }

    /**
     * Find players by team ID
     *
     * @param int $teamId Team ID
     * @return array Player entities
     */
    public function findByTeam(int $teamId): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.currentTeam = :teamId')
            ->setParameter('teamId', $teamId)
            ->orderBy('p.name', 'ASC');

        // Create cache key for this query
        $cacheKey = 'players_by_team_' . $teamId;

        // Create query with result cache
        $query = $this->createCachableQuery($qb, $cacheKey, 1800);

        return $query->getResult();
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
        // Get filename without extension
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

    /**
     * Count all players
     */
    public function countAll(): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)');

        // Use cache for this common operation
        $query = $this->createCachableQuery($qb, 'player_count_all', 3600);

        return (int)$query->getSingleScalarResult();
    }

    /**
     * Find players with the most tournament wins
     *
     * @param int $limit Maximum number of players to return
     * @return array Player entities
     */
    public function findTopPlayers(int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.currentTeam', 'ct')
            ->where('p.stats IS NOT NULL')
            ->orderBy('p.totalWon', 'DESC')
            ->setMaxResults($limit);

        // Create cache key
        $cacheKey = 'players_top_' . $limit;

        // Create query with result cache
        $query = $this->createCachableQuery($qb, $cacheKey, 3600);

        return $query->getResult();
    }

    /**
     * Find players with crosshair settings
     *
     * @param int $limit Maximum number of players to return
     * @return array Player entities
     */
    public function findWithCrosshair(int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.crosshair IS NOT NULL')
            ->orderBy('p.id', 'ASC')
            ->setMaxResults($limit);

        // Create cache key
        $cacheKey = 'players_with_crosshair_' . $limit;

        // Create query with result cache
        $query = $this->createCachableQuery($qb, $cacheKey, 3600);

        return $query->getResult();
    }
}