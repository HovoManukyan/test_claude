<?php

declare(strict_types=1);

namespace App\Service\Cache;

/**
 * Factory service for generating consistent cache keys
 */
class CacheKeyFactory
{
    /**
     * Cache namespace prefixes for different entity types
     */
    private const KEY_PREFIX_TEAM = 'team';
    private const KEY_PREFIX_PLAYER = 'player';
    private const KEY_PREFIX_BANNER = 'banner';
    private const KEY_PREFIX_TOURNAMENT = 'tournament';
    private const KEY_PREFIX_SKIN = 'skin';

    /**
     * Cache key types
     */
    private const TYPE_ENTITY = 'entity';
    private const TYPE_LIST = 'list';
    private const TYPE_PAGE = 'page';

    /**
     * @param string $environment Current application environment
     * @param bool $debug Whether application is in debug mode
     */
    public function __construct(
        private readonly string $environment,
        private readonly bool $debug
    ) {
    }

    /**
     * Generate a cache key for a team entity
     */
    public function teamEntity(int|string $identifier): string
    {
        return $this->buildKey(self::KEY_PREFIX_TEAM, self::TYPE_ENTITY, (string)$identifier);
    }

    /**
     * Generate a cache key for a team list with filters
     */
    public function teamList(int $page, int $limit, ?string $name = null, ?array $locales = null): string
    {
        $params = [
            'page' => $page,
            'limit' => $limit,
            'name' => $name,
            'locales' => $locales,
        ];

        return $this->buildKey(self::KEY_PREFIX_TEAM, self::TYPE_LIST, $this->hashParams($params));
    }

    /**
     * Generate a cache key for a player entity
     */
    public function playerEntity(int|string $identifier): string
    {
        return $this->buildKey(self::KEY_PREFIX_PLAYER, self::TYPE_ENTITY, (string)$identifier);
    }

    /**
     * Generate a cache key for a player by slug
     */
    public function playerBySlug(string $slug): string
    {
        return $this->buildKey(self::KEY_PREFIX_PLAYER, 'slug', $slug);
    }

    /**
     * Generate a cache key for a player list with filters
     */
    public function playerList(int $page, int $limit, ?bool $hasCrosshair = null, ?array $teamSlugs = [], ?string $name = null): string
    {
        $params = [
            'page' => $page,
            'limit' => $limit,
            'hasCrosshair' => $hasCrosshair,
            'teamSlugs' => $teamSlugs,
            'name' => $name,
        ];

        return $this->buildKey(self::KEY_PREFIX_PLAYER, self::TYPE_LIST, $this->hashParams($params));
    }

    /**
     * Generate a cache key for a banner entity
     */
    public function bannerEntity(int|string $identifier): string
    {
        return $this->buildKey(self::KEY_PREFIX_BANNER, self::TYPE_ENTITY, (string)$identifier);
    }

    /**
     * Generate a cache key for a banner by page
     */
    public function bannerByPage(string $pageIdentifier): string
    {
        return $this->buildKey(self::KEY_PREFIX_BANNER, self::TYPE_PAGE, $pageIdentifier);
    }

    /**
     * Generate a cache key for a tournament entity
     */
    public function tournamentEntity(int|string $identifier): string
    {
        return $this->buildKey(self::KEY_PREFIX_TOURNAMENT, self::TYPE_ENTITY, (string)$identifier);
    }

    /**
     * Generate a cache key for a tournament list with filters
     */
    public function tournamentList(int $page, int $limit, ?string $region = null, ?string $tier = null): string
    {
        $params = [
            'page' => $page,
            'limit' => $limit,
            'region' => $region,
            'tier' => $tier,
        ];

        return $this->buildKey(self::KEY_PREFIX_TOURNAMENT, self::TYPE_LIST, $this->hashParams($params));
    }

    /**
     * Generate a cache key for a skin entity
     */
    public function skinEntity(int|string $identifier): string
    {
        return $this->buildKey(self::KEY_PREFIX_SKIN, self::TYPE_ENTITY, (string)$identifier);
    }

    /**
     * Generate a cache key for a skin list with filters
     */
    public function skinList(int $page, int $limit, ?string $name = null): string
    {
        $params = [
            'page' => $page,
            'limit' => $limit,
            'name' => $name,
        ];

        return $this->buildKey(self::KEY_PREFIX_SKIN, self::TYPE_LIST, $this->hashParams($params));
    }

    /**
     * Build a cache key from parts
     */
    private function buildKey(string $prefix, string $type, string $identifier): string
    {
        // Include environment in cache key to avoid sharing cache between environments
        return sprintf('%s_%s_%s_%s', $this->environment, $prefix, $type, $identifier);
    }

    /**
     * Hash parameters to create a predictable identifier string
     */
    private function hashParams(array $params): string
    {
        // Sort params to ensure consistent order
        ksort($params);

        // Process array parameters
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                sort($value);
                $params[$key] = implode('_', $value);
            }

            // Convert null/bool values to strings
            if ($value === null) {
                $params[$key] = 'null';
            } elseif (is_bool($value)) {
                $params[$key] = $value ? 'true' : 'false';
            }
        }

        // Create a string representation of params
        $paramsString = http_build_query($params);

        // For readability in dev, keep the full key, but in prod we can use a hash
        if ($this->debug) {
            return $paramsString;
        }

        // Use md5 for fixed-length keys that are safe for cache backends
        return md5($paramsString);
    }
}