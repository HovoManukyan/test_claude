<?php

declare(strict_types=1);

namespace App\Service\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service for managing application cache
 */
class CacheService
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly CacheKeyFactory $cacheKeyFactory,
        private readonly LoggerInterface $logger,
        #[Autowire('%cache_ttl%')]
        private readonly array $cacheTtl,
    ) {
    }

    /**
     * Get an item from cache or compute it using the callback
     *
     * @template T
     * @param string $key The cache key
     * @param callable():T $callback Callback to compute the value if not cached
     * @param int|null $ttl Time to live in seconds
     * @return T The cached or computed value
     */
    public function get(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $cacheItem = $this->cache->getItem($key);

        if ($cacheItem->isHit()) {
            $this->logger->debug('Cache hit', ['key' => $key]);
            return $cacheItem->get();
        }

        $this->logger->debug('Cache miss', ['key' => $key]);
        $value = $callback();

        $cacheItem->set($value);

        if ($ttl !== null) {
            $cacheItem->expiresAfter($ttl);
        }

        $this->cache->save($cacheItem);

        return $value;
    }

    /**
     * Get cached team list or compute it
     */
    public function getTeamList(
        int $page,
        int $limit,
        ?string $name = null,
        ?array $locales = null,
        callable $callback
    ): mixed {
        $key = $this->cacheKeyFactory->createTeamListKey($page, $limit, $name, $locales);
        return $this->get($key, $callback, $this->cacheTtl['teams'] ?? 600);
    }

    /**
     * Get cached team details or compute them
     */
    public function getTeamDetail(string $slug, callable $callback): mixed
    {
        $key = $this->cacheKeyFactory->createTeamDetailKey($slug);
        return $this->get($key, $callback, $this->cacheTtl['teams'] ?? 600);
    }

    /**
     * Get cached player list or compute it
     */
    public function getPlayerList(
        int $page,
        int $limit,
        callable $callback,
        ?bool $hasCrosshair = null,
        ?array $teamSlugs = null,
        ?string $name = null,
    ): mixed {
        $key = $this->cacheKeyFactory->createPlayerListKey($page, $limit, $hasCrosshair, $teamSlugs, $name);
        return $this->get($key, $callback, $this->cacheTtl['players'] ?? 600);
    }

    /**
     * Get cached player details or compute them
     */
    public function getPlayerDetail(string $slug, callable $callback): mixed
    {
        $key = $this->cacheKeyFactory->createPlayerDetailKey($slug);
        return $this->get($key, $callback, $this->cacheTtl['players'] ?? 600);
    }

    /**
     * Get cached banner for a page or compute it
     */
    public function getBanner(string $page, callable $callback): mixed
    {
        $key = $this->cacheKeyFactory->createBannerKey($page);
        return $this->get($key, $callback, 3600); // Banners can be cached longer
    }

    /**
     * Invalidate a specific cache item
     */
    public function invalidate(string $key): bool
    {
        $this->logger->debug('Invalidating cache', ['key' => $key]);
        return $this->cache->deleteItem($key);
    }

    /**
     * Invalidate all cache items with a given prefix
     */
    public function invalidatePrefix(string $prefix): bool
    {
        $this->logger->debug('Invalidating cache with prefix', ['prefix' => $prefix]);
        // This is a simple implementation - actual implementation would depend on the cache adapter
        return true;
    }

    /**
     * Clear the entire cache
     */
    public function clear(): bool
    {
        $this->logger->debug('Clearing entire cache');
        return $this->cache->clear();
    }
}