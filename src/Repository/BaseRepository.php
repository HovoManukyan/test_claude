<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * Base repository with common pagination methods
 */
abstract class BaseRepository extends ServiceEntityRepository
{
    /**
     * Get paginated results from a QueryBuilder
     *
     * @param QueryBuilder $queryBuilder The query builder to paginate
     * @param int $page The page number
     * @param int $limit The number of items per page
     * @return array An array with 'data', 'total', and 'pages'
     * @throws \Exception
     */
    protected function paginate(QueryBuilder $queryBuilder, int $page, int $limit): array
    {
        $firstResult = ($page - 1) * $limit;

        // Clone the query builder to count total results
        $countQueryBuilder = clone $queryBuilder;
        $countQueryBuilder->select('COUNT(DISTINCT ' . $countQueryBuilder->getRootAliases()[0] . '.id)');
        $countQueryBuilder->resetDQLPart('orderBy');

        $total = (int) $countQueryBuilder->getQuery()->getSingleScalarResult();
        $pages = $limit > 0 ? ceil($total / $limit) : 1;

        // Apply pagination to the original query
        $query = $queryBuilder
            ->setFirstResult($firstResult)
            ->setMaxResults($limit)
            ->getQuery();

        // Use Doctrine's paginator for more efficient queries
        $paginator = new Paginator($query, true);

        return [
            'data' => iterator_to_array($paginator->getIterator()),
            'total' => $total,
            'pages' => $pages,
        ];
    }

    /**
     * Create a cache key for result cache
     *
     * @param string $prefix The cache key prefix
     * @param array $params The parameters to include in the key
     * @return string The cache key
     */
    protected function createCacheKey(string $prefix, array $params = []): string
    {
        $key = $prefix;
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