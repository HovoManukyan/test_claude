<?php

declare(strict_types=1);

namespace App\Service\Api;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Psr\Log\LoggerInterface;

class PandaScoreApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%pandascore.api_base_url%')]
        private readonly string $baseUrl,
        #[Autowire('%pandascore.token%')]
        private readonly string $apiToken,
    ) {
    }

    /**
     * Get list of players with pagination
     */
    public function getPlayers(int $page = 1, int $perPage = 100): array
    {
        return $this->makeRequest('/csgo/players', [
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * Get list of teams with pagination
     */
    public function getTeams(int $page = 1, int $perPage = 100): array
    {
        return $this->makeRequest('/csgo/teams', [
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * Get tournaments with pagination
     */
    public function getTournaments(int $page = 1, int $perPage = 100): array
    {
        return $this->makeRequest('/csgo/tournaments/past', [
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * Get player statistics
     */
    public function getPlayerStats(string $playerId): array
    {
        return $this->makeRequest("/csgo/players/{$playerId}/stats");
    }

    /**
     * Get team statistics
     */
    public function getTeamStats(string $teamId): array
    {
        return $this->makeRequest("/csgo/teams/{$teamId}/stats");
    }

    /**
     * Get detailed game information
     */
    public function getGame(string $gameId): array
    {
        return $this->makeRequest("/csgo/games/{$gameId}");
    }

    /**
     * Make a request to the PandaScore API
     */
    private function makeRequest(string $endpoint, array $queryParams = []): array
    {
        $url = $this->baseUrl . $endpoint;

        $this->logger->debug('Making request to PandaScore API', [
            'endpoint' => $endpoint,
            'params' => $queryParams,
        ]);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiToken,
                    'Accept' => 'application/json',
                ],
                'query' => $queryParams,
            ]);

            $data = $this->parseResponse($response);
            return $data;
        } catch (\Exception $e) {
            $this->logger->error('Error making request to PandaScore API', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Parse response from PandaScore API
     */
    private function parseResponse(ResponseInterface $response): array
    {
        $content = $response->getContent();
        $data = json_decode($content, true);

        if (!is_array($data)) {
            throw new \Exception('Invalid JSON response from PandaScore API');
        }

        return $data;
    }
}