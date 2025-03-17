<?php

declare(strict_types=1);

namespace App\Response;

use App\Entity\Team;

/**
 * Response for team list endpoints
 */
class TeamListResponse
{
    /**
     * @param TeamResponse[] $data
     */
    public function __construct(
        public readonly array $data,
        public readonly ?BannerResponse $banner,
        public readonly PaginationMeta $meta,
    ) {
    }

    /**
     * Create a response from team entities and pagination data
     */
    public static function fromEntities(
        array $teams,
        ?BannerResponse $banner,
        int $total,
        int $page,
        int $limit
    ): self {
        $teamsResponse = array_map(
            fn (Team $team) => TeamResponse::fromEntity($team),
            $teams
        );

        $meta = new PaginationMeta(
            total: $total,
            page: $page,
            limit: $limit,
            pages: $limit > 0 ? ceil($total / $limit) : 1,
        );

        return new self($teamsResponse, $banner, $meta);
    }
}