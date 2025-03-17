<?php
namespace App\DTO;

use App\Entity\Skin;

class SkinDTO
{
    public int $id;
    public string $name;
    public string $color;
    public string $image_id;
    public ?string $skin_link;
    public ?string $price;

    public function __construct(Skin $skin)
    {
        $this->id = $skin->getId();
        $this->name = $skin->getName();
        $this->color = $skin->getColor();
        $this->image_id = $skin->getImageId();
        $this->skin_link = $skin->getSkinLink();
        $this->price = $skin->getPrice();
    }

}