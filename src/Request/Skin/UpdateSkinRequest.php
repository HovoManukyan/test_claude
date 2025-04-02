<?php

declare(strict_types=1);

namespace App\Request\Skin;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateSkinRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    #[Assert\Type('string')]
    private  ?string $color = null;

    #[Assert\Type('string')]
    private  ?string $price = null;

    #[Assert\Type('string')]
    private  ?string $imageId = null;

    #[Assert\Type('string')]
    private  ?string $skinLink = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): void
    {
        $this->color = $color;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): void
    {
        $this->price = $price;
    }

    public function getImageId(): ?string
    {
        return $this->imageId;
    }

    public function setImageId(?string $imageId): void
    {
        $this->imageId = $imageId;
    }

    public function getSkinLink(): ?string
    {
        return $this->skinLink;
    }

    public function setSkinLink(?string $skinLink): void
    {
        $this->skinLink = $skinLink;
    }
}