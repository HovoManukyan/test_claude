<?php

declare(strict_types=1);

namespace App\Request\Team;

use Symfony\Component\Validator\Constraints as Assert;

final class TeamListRequest
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

        #[Assert\Length(
            max: 255,
            maxMessage: "Name cannot be longer than {{ limit }} characters"
        )]
        public readonly ?string $name = null,

        #[Assert\All([
            new Assert\NotBlank(message: "Locale cannot be blank"),
            new Assert\Length(
                min: 2,
                max: 5,
                minMessage: "Locale must be at least {{ limit }} characters",
                maxMessage: "Locale cannot be longer than {{ limit }} characters"
            )
        ])]
        public readonly array $locale = [],
    ) {
    }
}