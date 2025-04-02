<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * @method Team|null find($id, $lockMode = null, $lockVersion = null)
 * @method Team|null findOneBy(array $criteria, array $orderBy = null)
 * @method Team[]    findAll()
 * @method Team[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TeamRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly SluggerInterface $slugger,
    ) {
        parent::__construct($registry, Team::class);
    }

    /**
     * Поиск команд с фильтрами
     *
     * @param string|null $name Фильтр по имени
     * @param array|null $locations Фильтр по локациям
     * @return QueryBuilder
     */
    public function getSearchQueryBuilder(
        ?string $name = null,
        ?array $locations = null
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('t');

        if ($name) {
            $name = str_replace(['%', '_'], '', $name);

            $qb->andWhere(
                $qb->expr()->like('LOWER(t.name)', ':name')
            )->setParameter('name', '%' . strtolower($name) . '%');
        }

        if (!empty($locations)) {
            $qb->andWhere('t.location IN (:locations)')
                ->setParameter('locations', $locations);
        }

        return $qb->orderBy('t.name', 'ASC');
    }

    /**
     * Находит команду по slug с предзагрузкой связей
     *
     * @param string $slug Slug команды
     * @return Team|null Команда или null
     */
    public function findBySlugWithRelations(string $slug): ?Team
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.players', 'p')->addSelect('p')
            ->leftJoin('t.games', 'g')->addSelect('g')
            ->leftJoin('t.teamTournaments', 'tt')->addSelect('tt')
            ->where('t.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Находит команды по их slug
     *
     * @param array $slugs Slugs команд
     * @return Team[] Найденные команды
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
     * Генерирует уникальный slug для команды
     *
     * @param string $name Название команды
     * @param int|null $excludeId ID исключаемой команды
     * @return string Уникальный slug
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

        $existingSlugs = $qb->getQuery()->getResult();

        // Если slug уникален, возвращаем его
        if (empty($existingSlugs)) {
            return $slug;
        }

        // Извлекаем существующие номера
        $numbers = [];
        foreach ($existingSlugs as $row) {
            $existingSlug = $row['slug'];
            if ($existingSlug === $slug) {
                $numbers[] = 1;
            } elseif (preg_match('/' . preg_quote($slug, '/') . '-(\d+)$/', $existingSlug, $matches)) {
                $numbers[] = (int) $matches[1];
            }
        }

        // Находим следующий доступный номер
        $nextNumber = empty($numbers) ? 1 : max($numbers) + 1;

        return $slug . '-' . $nextNumber;
    }

    public function getTeamImageQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->where('t.image LIKE :prefix')
            ->setParameter('prefix', 'https://%');
    }

    public function findWithPlayersByPandascoreId(string $pandascoreId): ?Team
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.players', 'p')
            ->addSelect('p')
            ->where('t.pandascoreId = :pid')
            ->setParameter('pid', $pandascoreId)
            ->getQuery()
            ->getOneOrNullResult();
    }

}