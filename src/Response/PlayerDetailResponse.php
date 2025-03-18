<?php

declare(strict_types=1);

namespace App\Response;

use App\Entity\Banner;
use App\Entity\Player;

class PlayerDetailResponse
{
    /**
     * @param PlayerResponse $player Player data
     * @param BannerResponse|null $banner Optional banner to display
     */
    public function __construct(
        public readonly PlayerResponse $player,
        public readonly ?BannerResponse $banner = null,
    ) {
    }

    /**
     * Create from a Player entity and optional Banner
     *
     * @param Player $player Player entity
     * @param Banner|null $banner Banner entity
     * @return self Response DTO
     */
    public static function fromEntities(Player $player, ?Banner $banner): self
    {
        return new self(
            player: PlayerResponse::fromEntity($player),
            banner: $banner ? BannerResponse::fromEntity($banner) : null
        );
    }
}