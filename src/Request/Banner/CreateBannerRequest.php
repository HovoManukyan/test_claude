<?php

declare(strict_types=1);

namespace App\Request\Banner;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateBannerRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $title,

        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['default', 'promo'])]
        public readonly string $type,

        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $buttonText,

        #[Assert\Url]
        #[Assert\NotBlank]
        public readonly string $buttonLink,

        #[Assert\NotBlank]
        #[Assert\All([
            new Assert\Choice(choices: [
                'player_detail',
                'player_list',
                'team_detail',
                'team_list'
            ])
        ])]
        public readonly array $pages,

        #[Assert\Type('string')]
        public readonly ?string $promoText = null,

        #[Assert\Image(
            maxSize: '5M',
            mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
            mimeTypesMessage: 'Allowed image types are jpeg, png, webp.',
        )]
        public readonly ?UploadedFile $image = null,
    ) {
    }
}