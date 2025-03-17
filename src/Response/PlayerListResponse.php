<?php

declare(strict_types=1);

namespace App\Response;

use App\Entity\Player;

/**
 * Response for player list endpoints
 */
class PlayerListResponse
{
    /**
     * @param PlayerResponse[] $data
     * @param TeamResponse[]|null $selectedFilter
     */
    public function __construct(
        public readonly array $data,
        public readonly ?BannerResponse $banner,
        public readonly PaginationMeta $meta,
        public readonly ?array $selectedFilter = null,
    ) {
    }

    /**
     * Create a response from player entities and pagination data
     */
    public static function fromEntities(
        array $players,
        ?BannerResponse $banner,
        int $total,
        int $page,
        int $limit,
        ?array $selectedTeams = null
    ): self {
        $playersResponse = array_map(
            fn (Player $player) => PlayerResponse::fromEntity($player),
            $players
        );

        $meta = new PaginationMeta(
            total: $total,
            page: $page,
            limit: $limit,
            pages: $limit > 0 ? ceil($total / $limit) : 1,
        );

        $selectedFilter = null;
        if ($selectedTeams !== null) {
            $selectedFilter = array_map(
                fn ($team) => TeamResponse::fromEntity($team),
                $selectedTeams
            );
        }

        return new self($playersResponse, $banner, $meta, $selectedFilter);
    }
}