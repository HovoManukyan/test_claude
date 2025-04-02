<?php

declare(strict_types=1);

namespace App\Service\Http;

use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ChunkInterface;

class HttpClientService
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

    public function get(string $endpoint, array $queryParams = []): array
    {
        $url = $this->getFullUrl($endpoint);
        $this->logger->debug('Making GET request', ['url' => $url, 'params' => $queryParams]);

        $response = $this->httpClient->request('GET', $url, [
            'headers' => $this->getHeaders(),
            'query' => $queryParams,
        ]);

        return $this->processResponse($response);
    }

    public function post(string $endpoint, array $data = [], array $queryParams = []): array
    {
        $url = $this->getFullUrl($endpoint);
        $this->logger->debug('Making POST request', ['url' => $url, 'params' => $queryParams, 'data' => $data]);

        $response = $this->httpClient->request('POST', $url, [
            'headers' => $this->getHeaders(),
            'json' => $data,
            'query' => $queryParams,
        ]);

        return $this->processResponse($response);
    }

    public function downloadFile(string $url): string
    {
        $this->logger->debug('Downloading file', ['url' => $url]);

        $response = $this->httpClient->request('GET', $url);

        if ($response->getStatusCode() !== 200) {
            $this->logger->error('Failed to download file', ['url' => $url, 'status' => $response->getStatusCode()]);
            throw new RuntimeException("Failed to download file: HTTP {$response->getStatusCode()}");
        }

        return $response->getContent();
    }

    public function requestRaw(string $method, string $endpoint, array $options = []): ResponseInterface
    {
        $url = $this->getFullUrl($endpoint);
        return $this->httpClient->request($method, $url, array_merge([
            'headers' => $this->getHeaders(),
        ], $options));
    }

    public function streamResponses(array $responses): iterable
    {
        /** @var iterable<ChunkInterface> $stream */
        $stream = $this->httpClient->stream($responses);
        return $stream;
    }

    public function streamRequests(array $requests): iterable
    {
        return $this->httpClient->stream($requests);
    }

    public function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    public function getFullUrl(string $endpoint): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }

    private function processResponse(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->logger->error('API request failed', [
                'status' => $statusCode,
                'content' => substr($content, 0, 300),
            ]);
            throw new RuntimeException("API request failed: HTTP $statusCode - " . substr($content, 0, 100));
        }

        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            $this->logger->error('Failed to decode JSON response', [
                'content' => substr($content, 0, 300),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
