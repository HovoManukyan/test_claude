<?php

namespace App\Repository;

use App\Entity\Skin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Skin|null find($id, $lockMode = null, $lockVersion = null)
 * @method Skin|null findOneBy(array $criteria, array $orderBy = null)
 * @method Skin[] findAll()
 * @method Skin[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SkinRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Skin::class);
    }

    public function getSearchQueryBuilder(?string $name)
    {
        $qb = $this->createQueryBuilder('s');
        if ($name) {
            $name = str_replace(['%', '_'], '', $name);

            $qb->andWhere(
                $qb->expr()->like('s.name', ':name')
            )
                ->setParameter('name', '%' . strtolower($name) . '%');
        }
        return $qb->orderBy('s.name', 'ASC');
    }
}
