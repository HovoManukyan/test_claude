<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Базовый репозиторий с общими методами для всех репозиториев
 *
 * @template T of object
 * @extends ServiceEntityRepository<T>
 */
abstract class BaseRepository extends ServiceEntityRepository
{
    /**
     * @param ManagerRegistry $registry Реестр менеджеров Doctrine
     * @param class-string<T> $entityClass Класс сущности
     */
    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        parent::__construct($registry, $entityClass);
    }

    /**
     * Создаёт объект пагинации из QueryBuilder с подсчетом общего количества результатов
     *
     * @param QueryBuilder $queryBuilder Построитель запросов
     * @param int $page Номер страницы
     * @param int $limit Элементов на странице
     * @return array{data: T[], total: int, pages: int} Результат пагинации с метаданными
     */
    protected function paginate(QueryBuilder $queryBuilder, int $page, int $limit): array
    {
        $firstResult = ($page - 1) * $limit;

        // Клонируем QueryBuilder для подсчета общего количества результатов
        $countQueryBuilder = clone $queryBuilder;
        $countQueryBuilder->select('COUNT(DISTINCT ' . $countQueryBuilder->getRootAliases()[0] . '.id)')
            ->resetDQLPart('orderBy')
            ->resetDQLPart('groupBy');

        $total = (int)$countQueryBuilder->getQuery()
            ->getSingleScalarResult();

        $pages = $limit > 0 ? ceil($total / $limit) : 1;

        // Применяем пагинацию к оригинальному запросу
        $query = $queryBuilder
            ->setFirstResult($firstResult)
            ->setMaxResults($limit)
            ->getQuery();

        // Используем Doctrine Paginator для эффективной пагинации
        $paginator = new Paginator($query, true);

        return [
            'data' => iterator_to_array($paginator->getIterator()),
            'total' => $total,
            'pages' => $pages,
        ];
    }

    /**
     * Создаёт объект пагинации, возвращающий сам Paginator
     *
     * @param QueryBuilder $queryBuilder Построитель запросов
     * @param int $page Номер страницы
     * @param int $limit Элементов на странице
     * @return Paginator<T> Объект пагинатора
     */
    protected function createPaginator(QueryBuilder $queryBuilder, int $page, int $limit): Paginator
    {
        $firstResult = ($page - 1) * $limit;

        // Применяем пагинацию к запросу
        $query = $queryBuilder
            ->setFirstResult($firstResult)
            ->setMaxResults($limit)
            ->getQuery();

        // Используем Doctrine Paginator для эффективной пагинации
        return new Paginator($query, true);
    }

    /**
     * Возвращает общее количество объектов по заданным критериям
     *
     * @param array $criteria Критерии поиска
     * @return int Общее количество объектов
     */
    public function count(array $criteria = []): int
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)');

        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                $qb->andWhere(sprintf('e.%s IN (:%s)', $field, $field))
                    ->setParameter($field, $value);
            } else {
                $qb->andWhere(sprintf('e.%s = :%s', $field, $field))
                    ->setParameter($field, $value);
            }
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Находит случайную запись
     *
     * @param int $limit Максимальное количество записей
     * @return T[] Найденные записи
     */
    public function findRandom(int $limit = 1): array
    {
        $alias = 'e';

        $qb = $this->createQueryBuilder($alias);

        $qb->orderBy('random()');

        return $qb->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Обновляет сущность с заданными данными
     *
     * @param T $entity Сущность для обновления
     * @param array $data Данные для обновления
     * @return T Обновленная сущность
     */
    public function updateEntityWithData(object $entity, array $data): object
    {
        foreach ($data as $property => $value) {
            $setter = 'set' . ucfirst($property);

            if (method_exists($entity, $setter)) {
                $entity->$setter($value);
            }
        }

        $this->getEntityManager()->flush();

        return $entity;
    }

    /**
     * Сохраняет сущность
     *
     * @param T $entity Сущность для сохранения
     * @param bool $flush Нужно ли выполнять flush
     * @return T Сохраненная сущность
     */
    public function save(object $entity, bool $flush = true): object
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $entity;
    }

    /**
     * Удаляет сущность
     *
     * @param T $entity Сущность для удаления
     * @param bool $flush Нужно ли выполнять flush
     * @return void
     */
    public function remove(object $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Создает ключ кеша для запроса
     *
     * @param string $prefix Префикс ключа кеша
     * @param array $params Параметры для включения в ключ
     * @return string Ключ кеша
     */
    protected function createCacheKey(string $prefix, array $params = []): string
    {
        $key = $prefix;

        // Сортируем параметры для обеспечения постоянного порядка
        ksort($params);

        foreach ($params as $name => $value) {
            if (is_array($value)) {
                sort($value);
                $value = implode('_', $value);
            }

            if ($value === null) {
                $value = 'null';
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $key .= "_{$name}_{$value}";
        }

        return $key;
    }
}