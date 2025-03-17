<?php

namespace App\Repository;

use App\Entity\Banner;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Banner|null find($id, $lockMode = null, $lockVersion = null)
 * @method Banner|null findOneBy(array $criteria, array $orderBy = null)
 * @method Banner[]    findAll()
 * @method Banner[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BannerRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Banner::class);
    }

    /**
     * Find banners with pagination
     *
     * @param int $page Page number
     * @param int $limit Results per page
     * @return array Result with data, total and pages
     */
    public function findPaginated(int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('b')
            ->orderBy('b.createdAt', 'DESC');

        return $this->paginate($qb, $page, $limit);
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
            ->where("b.pages @> :page")
            ->setParameter('page', json_encode([$page]))
            ->orderBy('RANDOM()')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find banners by page
     *
     * @param string $page Page identifier
     * @return Banner[] Banner entities
     */
    public function findByPage(string $page): array
    {
        return $this->createQueryBuilder('b')
            ->where("b.pages @> :page")
            ->setParameter('page', json_encode([$page]))
            ->getQuery()
            ->getResult();
    }
}