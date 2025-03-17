<?php

declare(strict_types=1);

namespace App\Service\Http;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Base HTTP client service with common methods
 */
class HttpClientService
{
    /**
     * @param HttpClientInterface $httpClient The HTTP client
     * @param LoggerInterface $logger The logger
     * @param string $baseUrl Base API URL injected from configuration
     * @param string $apiToken API token injected from configuration
     */
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
     * Make a GET request to the API
     *
     * @param string $endpoint API endpoint (relative to base URL)
     * @param array $queryParams Query parameters
     * @return array Response data as array
     *
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws \JsonException
     */
    public function get(string $endpoint, array $queryParams = []): array
    {
        $url = $this->baseUrl . $endpoint;

        $this->logger->debug('Making GET request', [
            'url' => $url,
            'params' => $queryParams,
        ]);

        $response = $this->httpClient->request('GET', $url, [
            'headers' => $this->getHeaders(),
            'query' => $queryParams,
        ]);

        return $this->processResponse($response);
    }

    /**
     * Make a POST request to the API
     *
     * @param string $endpoint API endpoint (relative to base URL)
     * @param array $data Request body data
     * @param array $queryParams Query parameters
     * @return array Response data as array
     *
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws \JsonException
     */
    public function post(string $endpoint, array $data = [], array $queryParams = []): array
    {
        $url = $this->baseUrl . $endpoint;

        $this->logger->debug('Making POST request', [
            'url' => $url,
            'params' => $queryParams,
            'data' => $data,
        ]);

        $response = $this->httpClient->request('POST', $url, [
            'headers' => $this->getHeaders(),
            'json' => $data,
            'query' => $queryParams,
        ]);

        return $this->processResponse($response);
    }

    /**
     * Download a file from a URL
     *
     * @param string $url File URL
     * @return string File content
     *
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function downloadFile(string $url): string
    {
        $this->logger->debug('Downloading file', ['url' => $url]);

        $response = $this->httpClient->request('GET', $url);

        if ($response->getStatusCode() !== 200) {
            $this->logger->error('Failed to download file', [
                'url' => $url,
                'status' => $response->getStatusCode(),
            ]);

            throw new \RuntimeException(
                sprintf('Failed to download file: HTTP %d', $response->getStatusCode())
            );
        }

        return $response->getContent();
    }

    /**
     * Get default headers for API requests
     *
     * @return array Headers array
     */
    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Process HTTP response
     *
     * @param ResponseInterface $response HTTP response
     * @return array Response data
     *
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws \JsonException
     */
    private function processResponse(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->logger->error('API request failed', [
                'status' => $statusCode,
                'content' => $content,
            ]);

            throw new \RuntimeException(
                sprintf('API request failed: HTTP %d - %s', $statusCode, substr($content, 0, 100))
            );
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return $data;
        } catch (\JsonException $e) {
            $this->logger->error('Failed to decode JSON response', [
                'content' => substr($content, 0, 100),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}