<?php

namespace App\Utils;

class BannerCacheKeyFactory
{
    public static function getKeyForPageBanner(string $page): string
    {
        return "banners.page_{$page}";
    }
}