<?php

namespace App\Service\Pandascore\Fetcher;

class TeamFetcher extends AbstractFetcher
{
    protected int $perPage = 100;
    protected function getEndpoint(): string
    {
        return '/teams';
    }
}
