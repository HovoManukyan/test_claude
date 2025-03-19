<?php

declare(strict_types=1);

namespace App\Response\Banner;

use App\Entity\Banner;

class BannerResponse
{
    /**
     * @param int $id Banner ID
     * @param string $title Banner title
     * @param string $type Banner type
     * @param string $buttonText Button text
     * @param string $buttonLink Button link URL
     * @param array $pages Pages where the banner appears
     * @param string|null $image Banner image URL
     * @param string|null $promoText Promotional text
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $type,
        public readonly string $buttonText,
        public readonly string $buttonLink,
        public readonly array $pages,
        public readonly ?string $image = null,
        public readonly ?string $promoText = null,
    ) {
    }

    /**
     * Create from a Banner entity
     *
     * @param Banner $banner Banner entity
     * @return self Response DTO
     */
    public static function fromEntity(Banner $banner): self
    {
        return new self(
            id: $banner->getId(),
            title: $banner->getTitle(),
            type: $banner->getType(),
            buttonText: $banner->getButtonText(),
            buttonLink: $banner->getButtonLink(),
            pages: $banner->getPages(),
            image: $banner->getImage(),
            promoText: $banner->getPromoText()
        );
    }
}