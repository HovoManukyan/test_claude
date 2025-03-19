<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Player;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * @extends BaseRepository<Player>
 */
class PlayerRepository extends BaseRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly SluggerInterface $slugger,
    ) {
        parent::__construct($registry, Player::class);
    }

    /**
     * Поиск игроков с пагинацией и связями
     *
     * @param int $page Номер страницы
     * @param int $limit Элементов на странице
     * @param bool|null $hasCrosshair Фильтр по наличию прицела
     * @param array|null $teamSlugs Фильтр по командам
     * @param string|null $name Фильтр по имени
     * @return Paginator<Player> Пагинатор с игроками
     */
    public function findPaginatedWithRelations(
        int $page,
        int $limit,
        ?bool $hasCrosshair = null,
        ?array $teamSlugs = null,
        ?string $name = null
    ): Paginator {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.currentTeam', 'ct')->addSelect('ct');

        // Применяем фильтр по прицелу
        if ($hasCrosshair !== null) {
            $qb->andWhere($hasCrosshair ? 'p.crosshair IS NOT NULL' : 'p.crosshair IS NULL');
        }

        // Применяем фильтр по командам
        if (!empty($teamSlugs)) {
            $qb->join('p.teams', 't')
                ->andWhere('t.slug IN (:teamSlugs)')
                ->setParameter('teamSlugs', $teamSlugs);
        }

        // Применяем фильтр по имени
        if ($name !== null && $name !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('LOWER(p.firstName)', ':name'),
                    $qb->expr()->like('LOWER(p.lastName)', ':name'),
                    $qb->expr()->like('LOWER(p.name)', ':name')
                )
            )
                ->setParameter('name', '%' . strtolower($name) . '%');
        }

        // Сортировка по умолчанию
        $qb->orderBy('p.name', 'ASC');

        // Создаем экземпляр Doctrine Paginator
        return $this->createPaginator($qb, $page, $limit);
    }

    /**
     * Находит игрока по slug с предзагрузкой всех связей
     *
     * @param string $slug Slug игрока
     * @return Player|null Сущность игрока или null
     */
    public function findBySlugWithRelations(string $slug): ?Player
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.currentTeam', 'ct')->addSelect('ct')
            ->leftJoin('ct.players', 'ctp', 'WITH', 'ctp.slug != :slug')
            ->leftJoin('p.teams', 't')->addSelect('t')
            ->leftJoin('p.skins', 's')->addSelect('s')
            ->where('p.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->enableResultCache(3600, 'player_slug_' . $slug)
            ->getOneOrNullResult();
    }

    /**
     * Поиск игроков по ID команды
     *
     * @param int $teamId ID команды
     * @return Player[] Массив игроков
     */
    public function findByTeam(int $teamId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.currentTeam = :teamId')
            ->setParameter('teamId', $teamId)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->enableResultCache(1800, 'players_by_team_' . $teamId)
            ->getResult();
    }

    /**
     * Поиск игроков с наличием прицела
     *
     * @param int $limit Максимальное количество результатов
     * @return Player[] Массив игроков
     */
    public function findWithCrosshair(int $limit = 20): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.crosshair IS NOT NULL')
            ->orderBy('p.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->enableResultCache(3600, 'players_with_crosshair_' . $limit)
            ->getResult();
    }

    /**
     * Поиск топовых игроков по выигрышам
     *
     * @param int $limit Максимальное количество результатов
     * @return Player[] Массив игроков
     */
    public function findTopPlayers(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.currentTeam', 'ct')
            ->where('p.totalWon IS NOT NULL')
            ->orderBy('p.totalWon', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->enableResultCache(3600, 'players_top_' . $limit)
            ->getResult();
    }

    /**
     * Генерирует уникальный slug для игрока
     *
     * @param string $name Имя игрока
     * @param int|null $excludeId ID игрока для исключения (при обновлении)
     * @return string Уникальный slug
     */
    public function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug = $this->slugger->slug(strtolower($name))->toString();

        $qb = $this->createQueryBuilder('p')
            ->select('p.slug')
            ->where('p.slug = :slug OR p.slug LIKE :slug_pattern')
            ->setParameter('slug', $slug)
            ->setParameter('slug_pattern', $slug . '-%');

        if ($excludeId !== null) {
            $qb->andWhere('p.id != :excludeId')
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

    /**
     * Поиск игроков по набору ID
     *
     * @param array $ids Массив ID игроков
     * @return Player[] Массив игроков
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * Поиск игроков по стране
     *
     * @param string $nationality Код страны
     * @param int $limit Максимальное количество результатов
     * @return Player[] Массив игроков
     */
    public function findByNationality(string $nationality, int $limit = 20): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.nationality = :nationality')
            ->setParameter('nationality', $nationality)
            ->orderBy('p.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->enableResultCache(3600, 'players_by_nationality_' . $nationality . '_' . $limit)
            ->getResult();
    }

    /**
     * Поиск игроков с похожими значениями характеристики
     *
     * @param string $field Поле для сравнения
     * @param mixed $value Значение для сравнения
     * @param int $limit Максимальное количество результатов
     * @return Player[] Массив игроков
     */
    public function findSimilarByField(string $field, $value, int $limit = 5): array
    {
        if (!in_array($field, ['name', 'firstName', 'lastName', 'nationality'])) {
            throw new \InvalidArgumentException('Invalid field for comparison');
        }

        $qb = $this->createQueryBuilder('p')
            ->where("p.{$field} LIKE :value")
            ->setParameter('value', '%' . $value . '%')
            ->orderBy('p.name', 'ASC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Поиск случайных игроков
     *
     * @param int $limit Максимальное количество результатов
     * @return Player[] Массив игроков
     */
    public function findRandom(int $limit = 5): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $qb = $this->createQueryBuilder('p');

        $qb->orderBy('RANDOM()');

        return $qb->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}