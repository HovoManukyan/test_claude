<?php

declare(strict_types=1);

namespace App\Response;

use App\Entity\Team;

class TeamShortResponse
{
    /**
     * @param int $id Team ID
     * @param string $name Team name
     * @param string $slug Team slug
     * @param string|null $image Team image URL
     * @param string|null $location Team location code
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
     * Create from a Team entity
     *
     * @param Team $team Team entity
     * @return self Response DTO
     */
    public static function fromEntity(Team $team): self
    {
        return new self(
            id: $team->getId(),
            name: $team->getName(),
            slug: $team->getSlug(),
            image: $team->getImage(),
            location: $team->getLocation()
        );
    }
}