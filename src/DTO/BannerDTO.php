<?php

namespace App\DTO;

use App\Entity\Banner;

class BannerDTO
{
    public ?int $id;
    public ?string $title;
    public ?string $type;
    public ?string $buttonText;
    public ?string $promoText;
    public ?string $buttonLink;
    public array $pages;
    public ?string $image;

    public function __construct(Banner $banner)
    {
        $this->id = $banner->getId();
        $this->title = $banner->getTitle();
        $this->type = $banner->getType();
        $this->buttonText = $banner->getButtonText();
        $this->promoText = $banner->getPromoText();
        $this->buttonLink = $banner->getButtonLink();
        $this->pages = $banner->getPages();
        $this->image = $banner->getImage();
    }
}
