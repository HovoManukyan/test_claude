<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Trait for Doctrine query result caching functionality
 */
trait DoctrineResultCache
{
    /**
     * Execute a query with result cache
     *
     * @param QueryBuilder $qb The query builder
     * @param string $cacheKey Cache key
     * @param int|null $lifetime Cache lifetime in seconds (null for default)
     * @return Query The query with cache settings
     */
    protected function createCachableQuery(
        QueryBuilder $qb,
        string $cacheKey,
        ?int $lifetime = null
    ): Query {
        // Create the query
        $query = $qb->getQuery();

        // Enable result cache if we have a key and we're not in debug mode
        if ($cacheKey && !$this->isDebugMode()) {
            $query->enableResultCache($lifetime, $cacheKey);

            // Log cache usage if logger is available
            if (isset($this->logger) && $this->logger instanceof LoggerInterface) {
                $this->logger->debug('Using result cache for query', [
                    'cache_key' => $cacheKey,
                    'lifetime' => $lifetime,
                ]);
            }
        }

        return $query;
    }

    /**
     * Check if we're in debug mode
     *
     * @return bool True if in debug mode
     */
    private function isDebugMode(): bool
    {
        return isset($this->debugMode) && $this->debugMode === true;
    }

    /**
     * Create a cache key for a query with parameters
     *
     * @param string $prefix The cache key prefix
     * @param array $params The parameters for the query
     * @return string The cache key
     */
    protected function createQueryCacheKey(string $prefix, array $params = []): string
    {
        // Start with the prefix
        $key = $prefix;

        // Sort parameters by key to ensure consistent ordering
        ksort($params);

        // Build key from parameters
        foreach ($params as $paramName => $paramValue) {
            if (is_array($paramValue)) {
                // Sort arrays to ensure consistent ordering
                sort($paramValue);
                $paramValue = implode('_', $paramValue);
            } elseif ($paramValue === null) {
                $paramValue = 'null';
            } elseif (is_bool($paramValue)) {
                $paramValue = $paramValue ? 'true' : 'false';
            }

            $key .= '_' . $paramName . '_' . $paramValue;
        }

        // Hash long keys for database compatibility
        if (strlen($key) > 50) {
            $key = $prefix . '_' . md5($key);
        }

        return $key;
    }
}