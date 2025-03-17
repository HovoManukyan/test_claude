<?php

declare(strict_types=1);

namespace App\Value;

/**
 * Value object to represent paginated results
 *
 * @template T
 */
class PaginatedResult
{
    /**
     * @param array<T> $data The data items
     * @param int $total Total number of items
     * @param int $page Current page number
     * @param int $limit Items per page
     */
    public function __construct(
        public readonly array $data,
        public readonly int $total,
        public readonly int $page,
        public readonly int $limit
    ) {
    }

    /**
     * Get the total number of pages
     */
    public function getPages(): int
    {
        return $this->limit > 0 ? (int) ceil($this->total / $this->limit) : 1;
    }

    /**
     * Is there a next page?
     */
    public function hasNextPage(): bool
    {
        return $this->page < $this->getPages();
    }

    /**
     * Is there a previous page?
     */
    public function hasPreviousPage(): bool
    {
        return $this->page > 1;
    }

    /**
     * Convert to array with metadata
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'meta' => [
                'total' => $this->total,
                'page' => $this->page,
                'limit' => $this->limit,
                'pages' => $this->getPages(),
            ]
        ];
    }

    /**
     * Create from repository result
     *
     * @param array $result Repository result with 'data', 'total', and 'pages' keys
     * @param int $page Current page number
     * @param int $limit Items per page
     * @return self<T>
     */
    public static function fromRepositoryResult(array $result, int $page, int $limit): self
    {
        return new self(
            $result['data'],
            $result['total'],
            $page,
            $limit
        );
    }
}