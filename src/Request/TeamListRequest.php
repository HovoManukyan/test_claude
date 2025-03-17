<?php

declare(strict_types=1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

class TeamListRequest
{
    public function __construct(
        #[Assert\GreaterThanOrEqual(1)]
        public readonly int $page = 1,

        #[Assert\Range(min: 1, max: 100)]
        public readonly int $limit = 10,

        public readonly ?string $name = null,

        #[Assert\All([
            new Assert\NotBlank(),
            new Assert\Type('string')
        ])]
        public readonly array $locale = [],
    ) {
    }
}