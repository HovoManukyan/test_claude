<?php

declare(strict_types=1);

namespace App\Service\Cache;

use App\Entity\Banner;
use App\Entity\Player;
use App\Entity\Skin;
use App\Entity\Team;
use App\Entity\Tournament;
use App\Response\BannerResponse;
use App\Response\PlayerResponse;
use App\Response\SkinResponse;
use App\Response\TeamListResponse;
use App\Response\TeamResponse;
use App\Response\TournamentResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Centralized cache service for all application caching needs
 */
class CacheService
{
    /**
     * @param TagAwareCacheInterface $cache Cache adapter
     * @param CacheKeyFactory $cacheKeyFactory Factory for generating cache keys
     * @param LoggerInterface $logger Logger
     * @param array $cacheTtl Array of cache TTLs by entity type
     */
    public function __construct(
        private readonly TagAwareCacheInterface $cache,
        private readonly CacheKeyFactory $cacheKeyFactory,
        private readonly LoggerInterface $logger,
        #[Autowire('%cache_ttl%')]
        private readonly array $cacheTtl,
    ) {
    }

    /**
     * Get a team by slug from cache or compute it
     *
     * @param string $slug Team slug
     * @param callable $callback Function to compute the team if not in cache
     * @return Team|null Team entity or null
     */
    public function getTeam(string $slug, callable $callback): ?Team
    {
        $key = $this->cacheKeyFactory->teamEntity($slug);
        $ttl = $this->cacheTtl['teams'] ?? 3600;

        try {
            return $this->cache->get($key, function (ItemInterface $item) use ($callback, $ttl, $slug) {
                $item->expiresAfter($ttl);
                $item->tag(['teams', "team_$slug"]);

                return $callback();
            });
        } catch (\Throwable $e) {
            $this->logger->error('Error retrieving team from cache', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            // Fallback to direct computation
            return $callback();
        }
    }

    /**
     * Get team detail response from cache or compute it
     *
     * @param string $slug Team slug
     * @param callable $callback Function to compute the response if not in cache
     * @return array Response data
     */
    public function getTeamDetail(string $slug, callable $callback): array
    {
        $key = $this->cacheKeyFactory->teamEntity($slug);
        $ttl = $this->cacheTtl['teams'] ?? 3600;

        try {
            return $this->cache->get($key, function (ItemInterface $item) use ($callback, $ttl, $slug) {
                $item->expiresAfter($ttl);
                $item->tag(['teams', "team_$slug"]);

                return $callback();
            });
        } catch (\Throwable $e) {
            $this->logger->error('Error retrieving team detail from cache', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            // Fallback to direct computation
            return $callback();
        }
    }

    /**
     * Get team list response from cache or compute it
     *
     * @param int $page Page number
     * @param int $limit Results per page
     * @param string|null $name Filter by name
     * @param array|null $locales Filter by locations
     * @param callable $callback Function to compute the response if not in cache
     * @return TeamListResponse Response DTO
     */
    public function getTeamList(
        int $page,
        int $limit,
        ?string $name,
        ?array $locales,
        callable $callback
    ): TeamListResponse {
        $key = $this->cacheKeyFactory->teamList($page, $limit, $name, $locales);
        $ttl = $this->cacheTtl['teams'] ?? 3600;

        try {
            return $this->cache->get($key, function (ItemInterface $item) use ($callback, $ttl) {
                $item->expiresAfter($ttl);
                $item->tag(['teams', 'team_lists']);

                return $callback();
            });
        } catch (\Throwable $e) {
            $this->logger->error('Error retrieving team list from cache', [
                'page' => $page,
                'limit' => $limit,
                'name' => $name,
                'locales' => $locales,
                'error' => $e->getMessage(),
            ]);

            // Fallback to direct computation
            return $callback();
        }
    }

    /**
     * Get a player by slug from cache or compute it
     *
     * @param string $slug Player slug
     * @param callable $callback Function to compute the player if not in cache
     * @return Player|null Player entity or null
     */
    public function getPlayer(string $slug, callable $callback): ?Player
    {
        $key = $this->cacheKeyFactory->playerBySlug($slug);
        $ttl = $this->cacheTtl['players'] ?? 3600;

        try {
            return $this->cache->get($key, function (ItemInterface $item) use ($callback, $ttl, $slug) {
                $item->expiresAfter($ttl);
                $item->tag(['players', "player_$slug"]);

                return $callback();
            });
        } catch (\Throwable $e) {
            $this->logger->error('Error retrieving player from cache', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            // Fallback to direct computation
            return $callback();
        }
    }

    /**
     * Get player detail response from cache or compute it
     *
     * @param string $slug Player slug
     * @param callable $callback Function to compute the response if not in cache
     * @return array Response data
     */
    public function getPlayerDetail(string $slug, callable $callback): array
    {
        $key = $this->cacheKeyFactory->playerBySlug($slug);
        $ttl = $this->cacheTtl['players'] ?? 3600;

        try {
            return $this->cache->get($key, function (ItemInterface $item) use ($callback, $ttl, $slug) {
                $item->expiresAfter($ttl);
                $item->tag(['players', "player_$slug"]);

                return $callback();
            });
        } catch (\Throwable $e) {
            $this->logger->error('Error retrieving player detail from cache', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            // Fallback to direct computation
            return $callback();
        }
    }

    /**
     * Get player list response from cache or compute it
     *
     * @param int $page Page number
     * @param int $limit Results per page
     * @param bool|null $hasCrosshair Filter by crosshair presence
     * @param array|null $teamSlugs Filter by team slugs
     * @param string|null $name Filter by name
     * @param callable $callback Function to compute the response if not in cache
     * @return array Response data
     */
    public function getPlayerList(
        int $page,
        int $limit,
        ?bool $hasCrosshair,
        ?array $teamSlugs,
        ?string $name,
        callable $callback
    ): array {
        $key = $this->cacheKeyFactory->playerList($page, $limit, $hasCrosshair, $teamSlugs, $name);
        $ttl = $this->cacheTtl['players'] ?? 3600;

        try {
            return $this->cache->get($key, function (ItemInterface $item) use ($callback, $ttl) {
                $item->expiresAfter($ttl);
                $item->tag(['players', 'player_lists']);

                return $callback();
            });
        } catch (\Throwable $e) {
            $this->logger->error('Error retrieving player list from cache', [
                'page' => $page,
                'limit' => $limit,
                'hasCrosshair' => $hasCrosshair,
                'teamSlugs' => $teamSlugs,
                'name' => $name,
                'error' => $e->getMessage(),
            ]);

            // Fallback to direct computation
            return $callback();
        }
    }

    /**
     * Get a banner for a specific page from cache or compute it
     *
     * @param string $pageIdentifier Page identifier (e.g., 'player_list')
     * @param callable $callback Function to compute the banner if not in cache
     * @return Banner|null Banner entity or null
     */
    public function getBannerForPage(string $pageIdentifier, callable $callback): ?Banner
    {
        $key = $this->cacheKeyFactory->bannerByPage($pageIdentifier);
        $ttl = $this->cacheTtl['banners'] ?? 3600;

        try {
            return $this->cache->get($key, function (ItemInterface $item) use ($callback, $ttl) {
                $item->expiresAfter($ttl);
                $item->tag(['banners']);

                return $callback();
            });
        } catch (\Throwable $e) {
            $this->logger->error('Error retrieving banner from cache', [
                'page' => $pageIdentifier,
                'error' => $e->getMessage(),
            ]);

            // Fallback to direct computation
            return $callback();
        }
    }

    /**
     * Invalidate specific entity caches
     *
     * @param string $type Entity type ('team', 'player', 'banner', etc.)
     * @param string|int|null $identifier Entity identifier (optional)
     */
    public function invalidateEntity(string $type, string|int|null $identifier = null): void
    {
        $tags = [$type . 's'];

        if ($identifier !== null) {
            $tags[] = $type . '_' . $identifier;
        }

        $this->cache->invalidateTags($tags);
    }

    /**
     * Completely clear the cache
     */
    public function clearAll(): void
    {
        try {
            $this->cache->clear();
        } catch (\Throwable $e) {
            $this->logger->error('Error clearing cache', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}