<?php

namespace App\Repository;

use App\Entity\CronState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CronState|null find($id, $lockMode = null, $lockVersion = null)
 * @method CronState|null findOneBy(array $criteria, array $orderBy = null)
 * @method CronState[] findAll()
 * @method CronState[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CronStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CronState::class);
    }
}
