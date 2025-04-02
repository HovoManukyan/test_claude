<?php

namespace App\Service\Pandascore\Fetcher;

class TournamentFetcher extends AbstractFetcher
{
    protected int $perPage = 10;
    protected function getEndpoint(): string
    {
        return '/tournaments';
    }
}