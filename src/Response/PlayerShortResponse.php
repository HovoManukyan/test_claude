<?php

declare(strict_types=1);

namespace App\Response;

use App\Entity\Player;

class PlayerShortResponse
{
    /**
     * @param int $id Player ID
     * @param string $name Player name
     * @param string $slug Player slug
     * @param string|null $firstName First name
     * @param string|null $lastName Last name
     * @param string|null $image Player image URL
     * @param string|null $nationality Player nationality code
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $image = null,
        public readonly ?string $nationality = null,
    ) {
    }

    /**
     * Create from a Player entity
     *
     * @param Player $player Player entity
     * @return self Response DTO
     */
    public static function fromEntity(Player $player): self
    {
        return new self(
            id: $player->getId(),
            name: $player->getName(),
            slug: $player->getSlug(),
            firstName: $player->getFirstName(),
            lastName: $player->getLastName(),
            image: $player->getImage(),
            nationality: $player->getNationality()
        );
    }
}