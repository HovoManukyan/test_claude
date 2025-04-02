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
     *
     * @param Player $player Сущность игрока
     * @param Banner|null $banner Сущность баннера
     * @return self Объект ответа
     */
    public static function fromEntities(Player $player, ?Banner $banner): self
    {
        return new self(
            player: PlayerResponse::fromEntity($player),
            banner: $banner ? BannerResponse::fromEntity($banner) : null,
        );
    }

    /**
     * Создает объект ответа из массива данных
     *
     * @param array $data Массив данных
     * @return self Объект ответа
     */
    public static function fromArray(array $data): self
    {
        return new self(
            player: PlayerResponse::fromArray($data['player']),
            banner: isset($data['banner']) ? BannerResponse::fromArray($data['banner']) : null,
        );
    }
}