<?php

declare(strict_types=1);

namespace App\Response\Player;

use App\Entity\Player;

final class PlayerShortResponse
{
    /**
     * @param int $id Идентификатор игрока
     * @param string $name Имя игрока
     * @param string $slug Slug игрока
     * @param string|null $image Изображение
     * @param string|null $nationality Национальность
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $image = null,
        public readonly ?string $nationality = null,
    ) {
    }

    /**
     * Создает объект ответа из сущности
     *
     * @param Player $player Сущность игрока
     * @return self Объект ответа
     */
    public static function fromEntity(Player $player): self
    {
        return new self(
            id: $player->getId(),
            name: $player->getName(),
            slug: $player->getSlug(),
            image: $player->getImage(),
            nationality: $player->getNationality(),
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
            id: $data['id'],
            name: $data['name'],
            slug: $data['slug'],
            image: $data['image'] ?? null,
            nationality: $data['nationality'] ?? null,
        );
    }
}