<?php

declare(strict_types=1);

namespace App\Response;

use App\Entity\Banner;
use App\Entity\Player;
use App\Entity\Team;
use App\Value\PaginatedResult;

class PlayerListResponse
{
    /**
     * @param PlayerShortResponse[] $players List of players
     * @param BannerResponse|null $banner Banner for display
     * @param TeamShortResponse[]|null $selectedTeams Selected team filters
     * @param array $meta Pagination metadata
     */
    public function __construct(
        public readonly array $players,
        public readonly ?BannerResponse $banner,
        public readonly ?array $selectedTeams,
        public readonly array $meta,
    ) {
    }

    /**
     * Create from a paginated result and additional entities
     *
     * @param PaginatedResult<Player> $paginatedResult Paginated players
     * @param Banner|null $banner Banner entity
     * @param Team[]|null $selectedTeams Selected team filter entities
     * @return self Response DTO
     */
    public static function fromPaginatedResult(
        PaginatedResult $paginatedResult,
        ?Banner $banner,
        ?array $selectedTeams
    ): self {
        // Map players to response DTOs
        $playerResponses = array_map(
            fn(Player $player) => PlayerShortResponse::fromEntity($player),
            $paginatedResult->data
        );

        // Create banner response if banner exists
        $bannerResponse = $banner ? BannerResponse::fromEntity($banner) : null;

        // Map selected teams to response DTOs if present
        $teamResponses = null;
        if ($selectedTeams !== null) {
            $teamResponses = array_map(
                fn(Team $team) => TeamShortResponse::fromEntity($team),
                $selectedTeams
            );
        }

        return new self(
            $playerResponses,
            $bannerResponse,
            $teamResponses,
            [
                'total' => $paginatedResult->total,
                'page' => $paginatedResult->page,
                'limit' => $paginatedResult->limit,
                'pages' => $paginatedResult->getPages(),
            ]
        );
    }
}