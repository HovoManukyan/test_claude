<?php

namespace App\Repository;

use App\Entity\Banner;
use App\Utils\BannerCacheKeyFactory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Banner|null find($id, $lockMode = null, $lockVersion = null)
 * @method Banner|null findOneBy(array $criteria, array $orderBy = null)
 * @method Banner[]    findAll()
 * @method Banner[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BannerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Banner::class);
    }

    public function getSearchQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('b');
    }

    /**
     * Find one random banner for a specific page
     *
     * @param string $page Page identifier
     * @return Banner|null Banner entity or null if not found
     */
    public function findOneRandomByPage(string $page): ?Banner
    {
        // Using JSON containment operator @> to check if the page is in the pages array
        return $this->createQueryBuilder('b')
            ->where('JSONB_CONTAINS(b.pages, :page) = true')
            ->setParameter('page', json_encode([$page]))
            ->orderBy('RANDOM()')
            ->setMaxResults(1)
            ->getQuery()
            ->enableResultCache(3600, BannerCacheKeyFactory::getKeyForPageBanner($page))
            ->getOneOrNullResult();
    }
}