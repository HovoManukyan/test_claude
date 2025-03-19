<?php

declare(strict_types=1);

namespace App\Response\Player;

use App\Entity\Banner;
use App\Entity\Player;
use App\Response\Banner\BannerResponse;

final class PlayerDetailResponse
{
    /**
     * @param PlayerResponse $player Данные игрока
     * @param BannerResponse|null $banner Баннер страницы
     */
    public function __construct(
        public readonly PlayerResponse $player,
        public readonly ?BannerResponse $banner = null,
    ) {
    }

    /**
     * Создает объект ответа из сущностей
     */
    public static function fromEntities(Player $player, ?Banner $banner): self
    {
        return new self(
            player: PlayerResponse::fromEntity($player),
            banner: $banner ? BannerResponse::fromEntity($banner) : null,
        );
    }
}