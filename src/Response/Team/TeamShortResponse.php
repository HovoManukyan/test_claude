<?php

declare(strict_types=1);

namespace App\Response\Team;

use App\Entity\Team;

final class TeamShortResponse
{
    /**
     * @param int $id Идентификатор команды
     * @param string $name Название команды
     * @param string $slug Slug команды
     * @param string|null $image Изображение
     * @param string|null $location Локация
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $image = null,
        public readonly ?string $location = null,
    ) {
    }

    /**
     * Создает объект ответа из сущности
     */
    public static function fromEntity(Team $team): self
    {
        return new self(
            id: $team->getId(),
            name: $team->getName(),
            slug: $team->getSlug(),
            image: $team->getImage(),
            location: $team->getLocation(),
        );
    }
}