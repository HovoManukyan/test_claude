<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service for managing HTTP caching headers
 */
class HttpCacheService
{
    /**
     * @param array $cacheTtl Array of cache TTLs by entity type
     * @param string $environment Current environment
     */
    public function __construct(
        #[Autowire('%cache_ttl%')]
        private readonly array $cacheTtl,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    /**
     * Add cache headers to a response for teams
     */
    public function addTeamCacheHeaders(Response $response, string $etag = null): Response
    {
        $ttl = $this->cacheTtl['teams'] ?? 600;
        return $this->addCacheHeaders($response, $ttl, $etag);
    }

    /**
     * Add cache headers to a response for players
     */
    public function addPlayerCacheHeaders(Response $response, string $etag = null): Response
    {
        $ttl = $this->cacheTtl['players'] ?? 600;
        return $this->addCacheHeaders($response, $ttl, $etag);
    }

    /**
     * Add cache headers to a response for tournaments
     */
    public function addTournamentCacheHeaders(Response $response, string $etag = null): Response
    {
        $ttl = $this->cacheTtl['tournaments'] ?? 1800;
        return $this->addCacheHeaders($response, $ttl, $etag);
    }

    /**
     * Add cache headers to a response for banners
     */
    public function addBannerCacheHeaders(Response $response, string $etag = null): Response
    {
        $ttl = $this->cacheTtl['banners'] ?? 3600;
        return $this->addCacheHeaders($response, $ttl, $etag);
    }

    /**
     * Add generic cache headers to a response
     *
     * @param Response $response The response to modify
     * @param int $ttl Time to live in seconds
     * @param string|null $etag ETag value
     * @return Response The modified response
     */
    public function addCacheHeaders(Response $response, int $ttl, ?string $etag = null): Response
    {
        // Only add caching in production
        if ($this->environment === 'dev') {
            $response->headers->addCacheControlDirective('no-cache');
            $response->headers->addCacheControlDirective('no-store');
            $response->headers->addCacheControlDirective('must-revalidate');
            return $response;
        }

        $response->setPublic();
        $response->setMaxAge($ttl);
        $response->setSharedMaxAge($ttl);

        // Set Cache-Control: stale-while-revalidate directive for smoother updates
        $staleWhileRevalidate = min($ttl, 60); // Up to 1 minute
        $response->headers->add([
            'Cache-Control' => sprintf('stale-while-revalidate=%d', $staleWhileRevalidate)
        ]);

        if ($etag) {
            $response->setEtag($etag);
        }

        return $response;
    }

    /**
     * Generate ETag from data
     *
     * @param mixed $data The data to generate ETag from
     * @return string The generated ETag
     */
    public function generateEtag($data): string
    {
        if (is_array($data) || is_object($data)) {
            $data = json_encode($data);
        }

        return md5((string)$data);
    }
}