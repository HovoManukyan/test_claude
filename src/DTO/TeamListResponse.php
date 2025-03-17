<?php

declare(strict_types=1);

namespace App\Response;

use App\Entity\Banner;
use App\Entity\Team;
use App\Value\PaginatedResult;

class TeamListResponse
{
    /**
     * @param TeamResponse[] $teams
     * @param BannerResponse|null $banner
     * @param array $meta Pagination metadata
     */
    public function __construct(
        public readonly array $teams,
        public readonly ?BannerResponse $banner,
        public readonly array $meta,
    ) {
    }

    /**
     * Create from a paginated result
     *
     * @param PaginatedResult<Team> $paginatedResult
     * @param Banner|null $banner
     * @return self
     */
    public static function fromPaginatedResult(PaginatedResult $paginatedResult, ?Banner $banner): self
    {
        // Map teams to response DTOs
        $teamResponses = array_map(
            fn(Team $team) => TeamResponse::fromEntity($team),
            $paginatedResult->data
        );

        // Create banner response if banner exists
        $bannerResponse = $banner ? BannerResponse::fromEntity($banner) : null;

        return new self(
            $teamResponses,
            $bannerResponse,
            [
                'total' => $paginatedResult->total,
                'page' => $paginatedResult->page,
                'limit' => $paginatedResult->limit,
                'pages' => $paginatedResult->getPages(),
            ]
        );
    }
}