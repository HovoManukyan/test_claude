<?php

namespace App\Service\Pandascore\Fetcher;

use App\Service\Http\HttpClientService;
use Symfony\Contracts\HttpClient\ResponseInterface;

abstract class AbstractFetcher
{
    abstract protected function getEndpoint(): string;
    protected const MAX_CONCURRENT_REQUESTS = 2;
    protected const MAX_REQUESTS_PER_SECOND = 2;
    protected int $perPage = 100;

    public function __construct(
        protected readonly HttpClientService $http,
    ) {}

    public function fetchAllPages(callable $onPageFetched): void
    {
        $page = 1;
        $pendingRequests = [];
        $lastRequestTimes = [];

        while (true) {
            while (count($pendingRequests) < self::MAX_CONCURRENT_REQUESTS) {
                $this->respectRateLimit($lastRequestTimes);

                $attempts = 0;
                $maxAttempts = 3;

                do {
                    try {
                        $request = $this->http->requestRaw('GET', $this->getEndpoint(), [
                            'query' => [
                                'page' => $page,
                                'per_page' => $this->perPage,
                            ],
                        ]);

                        $pendingRequests[] = $request;
                        $lastRequestTimes[] = microtime(true);
                        $page++;
                        break; // запрос успешен — выходим из попыток
                    } catch (\Throwable $e) {
                        $attempts++;
                        echo "⚠️ Ошибка подключения на странице $page. Попытка $attempts/$maxAttempts. Повтор через 5 сек...\n";
                        sleep(5);
                    }
                } while ($attempts < $maxAttempts);

                // если даже после повторов не получилось — скипаем эту страницу
                if ($attempts >= $maxAttempts) {
                    echo "❌ Пропуск страницы $page после $maxAttempts неудачных попыток\n";
                    $page++;
                }
            }

            foreach ($this->http->streamResponses($pendingRequests) as $response => $chunk) {
                array_shift($pendingRequests);

                try {
                    $data = $response->toArray();
                } catch (\Throwable $e) {
                    echo "⚠️ Ошибка при получении данных. Пропуск батча. " . $e->getMessage() . "\n";
                    continue;
                }

                unset($response, $chunk);

                if (empty($data)) {
                    return;
                }

                $onPageFetched($data);

                echo "🧠 mem: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB\n";
            }
        }
    }


    private function respectRateLimit(array &$timestamps): void
    {
        $now = microtime(true);
        $oneSecondAgo = $now - 1;

        $timestamps = array_filter(
            $timestamps,
            fn($t) => $t > $oneSecondAgo
        );

        if (count($timestamps) >= self::MAX_REQUESTS_PER_SECOND) {
            usleep(500_000);
        }
    }
}
