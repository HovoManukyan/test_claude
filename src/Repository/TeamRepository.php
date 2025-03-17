<?php

namespace App\Repository;

use App\Entity\Team;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * @method Team|null find($id, $lockMode = null, $lockVersion = null)
 * @method Team|null findOneBy(array $criteria, array $orderBy = null)
 * @method Team[]    findAll()
 * @method Team[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TeamRepository extends BaseRepository
{
    private SluggerInterface $slugger;

    public function __construct(ManagerRegistry $registry, SluggerInterface $slugger)
    {
        parent::__construct($registry, Team::class);
        $this->slugger = $slugger;
    }

    /**
     * Find teams with pagination and filters
     *
     * @param int $page Page number
     * @param int $limit Results per page
     * @param string|null $name Filter by name
     * @param array|null $locales Filter by locations
     * @return array Result with data, total and pages
     */
    public function findPaginated(int $page, int $limit, ?string $name = null, ?array $locales = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.players', 'p');

        // Apply filters
        if ($name !== null && $name !== '') {
            $qb->andWhere('LOWER(t.name) LIKE LOWER(:name)')
                ->setParameter('name', '%' . trim($name) . '%');
        }

        if (!empty($locales)) {
            $qb->andWhere('t.location IN (:locales)')
                ->setParameter('locales', $locales);
        }

        // Add order by
        $qb->orderBy('t.name', 'ASC');

        // Use base paginator
        return $this->paginate($qb, $page, $limit);
    }

    /**
     * Find a team by slug
     *
     * @param string $slug Team slug
     * @return Team|null Team entity or null if not found
     */
    public function findOneBySlug(string $slug): ?Team
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.players', 'p')
            ->leftJoin('t.games', 'g')
            ->leftJoin('t.teamTournaments', 'tt')
            ->where('t.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find teams by their slugs
     *
     * @param array $slugs Team slugs
     * @return array Team entities
     */
    public function findBySlug(array $slugs): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.slug IN (:slugs)')
            ->setParameter('slugs', $slugs)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get random teams
     *
     * @param int $limit Number of teams to return
     * @return array Team entities
     */
    public function findRandom(int $limit = 12): array
    {
        // Using the native SQL for Postgres random() function
        return $this->createQueryBuilder('t')
            ->orderBy('RANDOM()')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Generate a unique slug for a team
     *
     * @param string $name Team name
     * @param int|null $excludeId Exclude team ID from uniqueness check
     * @return string Unique slug
     */
    public function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug = $this->slugger->slug(strtolower($name))->toString();

        $qb = $this->createQueryBuilder('t')
            ->select('t.slug')
            ->where('t.slug = :slug OR t.slug LIKE :slug_pattern')
            ->setParameter('slug', $slug)
            ->setParameter('slug_pattern', $slug . '-%');

        if ($excludeId !== null) {
            $qb->andWhere('t.id != :excludeId')
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