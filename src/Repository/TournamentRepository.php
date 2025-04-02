<?php

namespace App\Repository;

use App\Entity\Tournament;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Tournament|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tournament|null findOneBy(array $criteria, array $orderBy = null)
 * @method Tournament[]    findAll()
 * @method Tournament[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TournamentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tournament::class);
    }

    /**
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function findAllWithExpectedRosterPaginated(int $page, int $limit): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.expectedRoster IS NOT NULL')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

}