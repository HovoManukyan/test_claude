<?php

declare(strict_types=1);

namespace App\Response;

use App\Entity\Skin;

class SkinResponse
{
    /**
     * @param int $id Skin ID
     * @param string $name Skin name
     * @param string $color Skin color
     * @param int|null $imageId Image ID
     * @param string|null $skinLink Link to skin
     * @param float|null $price Skin price
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $color,
        public readonly ?int $imageId = null,
        public readonly ?string $skinLink = null,
        public readonly ?float $price = null,
    ) {
    }

    /**
     * Create from a Skin entity
     *
     * @param Skin $skin Skin entity
     * @return self Response DTO
     */
    public static function fromEntity(Skin $skin): self
    {
        return new self(
            id: $skin->getId(),
            name: $skin->getName(),
            color: $skin->getColor(),
            imageId: $skin->getImageId(),
            skinLink: $skin->getSkinLink(),
            price: $skin->getPrice()
        );
    }
}