<?php

namespace App\Repository;

use App\Entity\Tournament;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Tournament|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tournament|null findOneBy(array $criteria, array $orderBy = null)
 * @method Tournament[]    findAll()
 * @method Tournament[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TournamentRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tournament::class);
    }

    /**
     * Find tournaments with pagination
     *
     * @param int $page Page number
     * @param int $limit Results per page
     * @param string|null $region Filter by region
     * @param string|null $tier Filter by tier
     * @return array Result with data, total and pages
     */
    public function findPaginated(
        int $page,
        int $limit,
        ?string $region = null,
        ?string $tier = null
    ): array {
        $qb = $this->createQueryBuilder('t');

        // Apply region filter
        if ($region !== null && $region !== '') {
            $qb->andWhere('t.region = :region')
                ->setParameter('region', $region);
        }

        // Apply tier filter
        if ($tier !== null && $tier !== '') {
            $qb->andWhere('t.tier = :tier')
                ->setParameter('tier', $tier);
        }

        // Add order by
        $qb->orderBy('t.beginAt', 'DESC');

        // Use base paginator
        return $this->paginate($qb, $page, $limit);
    }

    /**
     * Find tournaments for a team
     *
     * @param int $teamId Team ID
     * @param int $limit Maximum number of tournaments to return
     * @return Tournament[] Tournament entities
     */
    public function findByTeam(int $teamId, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.teams', 'tm')
            ->where('tm.id = :teamId')
            ->setParameter('teamId', $teamId)
            ->orderBy('t.beginAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find tournaments for a player
     *
     * @param int $playerId Player ID
     * @param int $limit Maximum number of tournaments to return
     * @return Tournament[] Tournament entities
     */
    public function findByPlayer(int $playerId, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.players', 'p')
            ->where('p.id = :playerId')
            ->setParameter('playerId', $playerId)
            ->orderBy('t.beginAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find upcoming tournaments
     *
     * @param int $limit Maximum number of tournaments to return
     * @return Tournament[] Tournament entities
     */
    public function findUpcoming(int $limit = 10): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('t')
            ->where('t.beginAt > :now')
            ->setParameter('now', $now)
            ->orderBy('t.beginAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find tournaments by their PandaScore IDs
     *
     * @param array $ids PandaScore tournament IDs
     * @return Tournament[] Tournament entities
     */
    public function findByPandaScoreIds(array $ids): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.tournamentId IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find tournaments without players or teams
     *
     * @param int $limit Maximum number of tournaments to process
     * @return Tournament[] Tournament entities
     */
    public function findWithoutRelations(int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.players', 'p')
            ->leftJoin('t.teams', 'tm')
            ->groupBy('t.id')
            ->having('COUNT(p.id) = 0 AND COUNT(tm.id) = 0')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}