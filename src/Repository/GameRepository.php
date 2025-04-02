<?php

namespace App\Repository;

use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Game|null find($id, $lockMode = null, $lockVersion = null)
 * @method Game|null findOneBy(array $criteria, array $orderBy = null)
 * @method Game[]    findAll()
 * @method Game[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /**
     * Find games with pagination
     *
     * @param int $page Page number
     * @param int $limit Results per page
     * @param string|null $status Filter by status
     * @param int|null $tournamentId Filter by tournament ID
     * @return array Result with data, total and pages
     */
    public function findPaginated(
        int $page,
        int $limit,
        ?string $status = null,
        ?int $tournamentId = null
    ): array {
        $qb = $this->createQueryBuilder('g')
            ->leftJoin('g.teams', 't')
            ->leftJoin('g.tournament', 'tr');

        // Apply status filter
        if ($status !== null && $status !== '') {
            $qb->andWhere('g.status = :status')
                ->setParameter('status', $status);
        }

        // Apply tournament filter
        if ($tournamentId !== null) {
            $qb->andWhere('g.tournament = :tournamentId')
                ->setParameter('tournamentId', $tournamentId);
        }

        // Add order by
        $qb->orderBy('g.beginAt', 'DESC');

        // Use base paginator
        return $this->paginate($qb, $page, $limit);
    }

    /**
     * Find games for a team
     *
     * @param int $teamId Team ID
     * @param int $limit Maximum number of games to return
     * @return Game[] Game entities
     */
    public function findByTeam(int $teamId, int $limit = 10): array
    {
        return $this->createQueryBuilder('g')
            ->join('g.teams', 't')
            ->where('t.id = :teamId')
            ->setParameter('teamId', $teamId)
            ->orderBy('g.beginAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find games for a player
     *
     * @param int $playerId Player ID
     * @param int $limit Maximum number of games to return
     * @return Game[] Game entities
     */
    public function findByPlayer(int $playerId, int $limit = 10): array
    {
        return $this->createQueryBuilder('g')
            ->join('g.players', 'p')
            ->where('p.id = :playerId')
            ->setParameter('playerId', $playerId)
            ->orderBy('g.beginAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find upcoming games
     *
     * @param int $limit Maximum number of games to return
     * @return Game[] Game entities
     */
    public function findUpcoming(int $limit = 10): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('g')
            ->where('g.beginAt > :now')
            ->andWhere('g.status = :status')
            ->setParameter('now', $now)
            ->setParameter('status', 'upcoming')
            ->orderBy('g.beginAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recently completed games
     *
     * @param int $limit Maximum number of games to return
     * @return Game[] Game entities
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.status = :status')
            ->setParameter('status', 'finished')
            ->orderBy('g.endAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}