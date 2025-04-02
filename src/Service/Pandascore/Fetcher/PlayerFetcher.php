<?php

namespace App\Service\Pandascore\Fetcher;

class PlayerFetcher extends AbstractFetcher
{
    protected int $perPage = 100;
    protected function getEndpoint(): string
    {
        return '/players';
    }
}
