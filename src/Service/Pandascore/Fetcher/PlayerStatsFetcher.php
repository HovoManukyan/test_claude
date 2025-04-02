<?php

namespace App\Service\Pandascore\Fetcher;

use App\Entity\Player;
use App\Service\Http\HttpClientService;

class PlayerStatsFetcher
{
    public function __construct(
        private readonly HttpClientService $http,
    ) {}

    /**
     * @param Player[] $players
     * @return iterable<Player, array> - генератор с игроками и их данными
     */
    public function fetchStats(array $players): iterable
    {
        $playerRequests = [];

        foreach ($players as $player) {
            $endpoint = "/players/{$player->getSlug()}/stats";
            echo "Creating request for player: {$player->getName()} - endpoint: $endpoint\n";
            $request = $this->http->requestRaw('GET', $endpoint);
            $playerRequests[] = [
                'player' => $player,
                'request' => $request
            ];
        }

        foreach ($playerRequests as $playerRequest) {
            $player = $playerRequest['player'];
            $request = $playerRequest['request'];

            try {
                echo "Processing response for player: {$player->getName()}\n";
                $response = $request->toArray();
                yield $player => $response;
            } catch (\Throwable $e) {
                echo "⚠️ Ошибка: {$player->getName()} ({$player->getSlug()}) - {$e->getMessage()}\n";
                yield $player => [];
            }
        }
    }
}