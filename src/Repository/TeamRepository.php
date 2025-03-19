<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Team;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * @extends BaseRepository<Team>
 */
class TeamRepository extends BaseRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly SluggerInterface $slugger,
    ) {
        parent::__construct($registry, Team::class);
    }

    /**
     * Поиск команд с пагинацией и связями
     *
     * @param int $page Номер страницы
     * @param int $limit Элементов на странице
     * @param string|null $name Фильтр по имени
     * @param array|null $locales Фильтр по локациям
     * @return Paginator<Team> Пагинатор с командами
     */
    public function findPaginatedWithRelations(
        int $page,
        int $limit,
        ?string $name = null,
        ?array $locales = null
    ): Paginator {
        $qb = $this->createQueryBuilder('t')
            // Предзагрузка основных связей
            ->leftJoin('t.players', 'p')->addSelect('p');

        // Применяем фильтр по имени
        if ($name !== null && $name !== '') {
            $qb->andWhere('LOWER(t.name) LIKE LOWER(:name)')
                ->setParameter('name', '%' . trim($name) . '%');
        }

        // Применяем фильтр по локациям
        if (!empty($locales)) {
            $qb->andWhere('t.location IN (:locales)')
                ->setParameter('locales', $locales);
        }

        // Сортировка по умолчанию
        $qb->orderBy('t.name', 'ASC');

        // Создаем экземпляр Doctrine Paginator
        return $this->createPaginator($qb, $page, $limit);
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
}