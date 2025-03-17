<?php

declare(strict_types=1);

namespace App\Response;

/**
 * Standardized metadata for paginated responses
 */
class PaginationMeta
{
    public function __construct(
        public readonly int $total,
        public readonly int $page,
        public readonly int $limit,
        public readonly int $pages,
    ) {
    }
}