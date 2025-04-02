<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Player;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Player|null find($id, $lockMode = null, $lockVersion = null)
 * @method Player|null findOneBy(array $criteria, array $orderBy = null)
 * @method Player[]    findAll()
 * @method Player[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PlayerRepository extends ServiceEntityRepository
{
    /**
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Player::class);
    }

    /**
     * Поиск игроков с пагинацией и связями
     *
     * @param bool|null $hasCrosshair Фильтр по наличию прицела
     * @param array|null $teamSlugs Фильтр по командам
     * @param string|null $name Фильтр по имени
     * @return QueryBuilder
     */
    public function getSearchQueryBuilder(
        ?bool $hasCrosshair = null,
        ?array $teamSlugs = null,
        ?string $name = null
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.currentTeam', 'ct')->addSelect('ct');

        // Применяем фильтр по прицелу
        if ($hasCrosshair !== null) {
            $qb->andWhere('p.crosshair IS NOT NULL');
        }

        // Применяем фильтр по командам
        if (!empty($teamSlugs)) {
            $qb->join('p.teams', 't')
                ->andWhere('t.slug IN (:teamSlugs)')
                ->setParameter('teamSlugs', $teamSlugs);
        }

        if ($name) {
            $name = str_replace(['%', '_'], '', $name);

            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('p.firstName', ':name'),
                    $qb->expr()->like('p.lastName', ':name'),
                    $qb->expr()->like('p.name', ':name')
                )
            )
                ->setParameter('name', '%' . strtolower($name) . '%');
        }

        return $qb->orderBy('p.name', 'ASC');
    }

    function getPlayerImageQueryBuilder(): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.image LIKE :prefix')
            ->setParameter('prefix', 'https://%');
        return $qb;
    }

    public function findByTeamId(int $teamId): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.teams', 't')
            ->andWhere('t.id = :id')
            ->setParameter('id', $teamId)
            ->getQuery()
            ->getResult();
    }

    public function findPaginated(int $page, int $limit): array
    {
        return $this->createQueryBuilder('p')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}