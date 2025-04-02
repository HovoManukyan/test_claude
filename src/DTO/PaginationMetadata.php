<?php

namespace App\DTO;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Serializer\Attribute\Groups;

#[Exclude]
#[Groups('paginator')]
final class PaginationMetadata
{
    public function __construct(
        public int $page,
        public int $limit,
        public int $pages,
        public int $total
    ) {
    }

    public static function empty(): self
    {
        return new PaginationMetadata(1, 10, 0, 0);
    }
}
