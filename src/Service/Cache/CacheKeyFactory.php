<?php

declare(strict_types=1);

namespace App\Service\Cache;

/**
 * Service for generating standardized cache keys
 */
class CacheKeyFactory
{
    public function __construct(
        private readonly string $environment,
        private readonly bool $debug
    ) {
    }

    /**
     * Generate a team list cache key
     */
    public function createTeamListKey(int $page, int $limit, ?string $name = null, ?array $locales = null): string
    {
        $components = [
            'teams',
            'list',
            "page_{$page}",
            "limit_{$limit}",
        ];

        if ($name !== null) {
            $components[] = "name_" . md5($name);
        }

        if ($locales !== null && !empty($locales)) {
            sort($locales); // Ensure consistent ordering
            $components[] = "locales_" . md5(implode('_', $locales));
        }

        return $this->createKey($components);
    }

    /**
     * Generate a team detail cache key
     */
    public function createTeamDetailKey(string $slug): string
    {
        return $this->createKey(['teams', 'detail', $slug]);
    }

    /**
     * Generate a player list cache key
     */
    public function createPlayerListKey(
        int $page,
        int $limit,
        ?bool $hasCrosshair = null,
        ?array $teamSlugs = null,
        ?string $name = null
    ): string {
        $components = [
            'players',
            'list',
            "page_{$page}",
            "limit_{$limit}",
        ];

        if ($hasCrosshair !== null) {
            $components[] = "crosshair_" . ($hasCrosshair ? 'yes' : 'no');
        }

        if ($teamSlugs !== null && !empty($teamSlugs)) {
            sort($teamSlugs); // Ensure consistent ordering
            $components[] = "teams_" . md5(implode('_', $teamSlugs));
        }

        if ($name !== null) {
            $components[] = "name_" . md5($name);
        }

        return $this->createKey($components);
    }

    /**
     * Generate a player detail cache key
     */
    public function createPlayerDetailKey(string $slug): string
    {
        return $this->createKey(['players', 'detail', $slug]);
    }

    /**
     * Generate a banner cache key
     */
    public function createBannerKey(string $page): string
    {
        return $this->createKey(['banners', $page]);
    }

    /**
     * Generate a tournament list cache key
     */
    public function createTournamentListKey(int $page, int $limit): string
    {
        return $this->createKey(['tournaments', 'list', "page_{$page}", "limit_{$limit}"]);
    }

    /**
     * Generate a tournament detail cache key
     */
    public function createTournamentDetailKey(string $slug): string
    {
        return $this->createKey(['tournaments', 'detail', $slug]);
    }

    /**
     * Create a standardized cache key with environment prefix
     */
    private function createKey(array $components): string
    {
        // Add environment to prevent cache collisions across environments
        array_unshift($components, $this->environment);

        // Add debug flag to prevent cache issues during development
        if ($this->debug) {
            $components[] = 'debug';
        }

        return implode('_', $components);
    }
}