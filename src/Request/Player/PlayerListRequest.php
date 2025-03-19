<?php

declare(strict_types=1);

namespace App\Request\Player;

use Symfony\Component\Validator\Constraints as Assert;

final class PlayerListRequest
{
    public function __construct(
        #[Assert\GreaterThanOrEqual(1, message: "Page must be at least 1")]
        public readonly int $page = 1,

        #[Assert\Range(
            notInRangeMessage: "Limit must be between {{ min }} and {{ max }}",
            min: 1,
            max: 100
        )]
        public readonly int $limit = 10,

        #[Assert\Type(type: "bool", message: "Has crosshair must be a boolean value")]
        public readonly ?bool $hasCrosshair = null,

        #[Assert\All([
            new Assert\Type(type: "string", message: "Each team slug must be a string")
        ])]
        public readonly array $teamSlugs = [],

        #[Assert\Length(
            max: 255,
            maxMessage: "Search name cannot be longer than {{ limit }} characters"
        )]
        public readonly ?string $name = null,
    ) {
    }
}