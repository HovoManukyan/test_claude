<?php
namespace App\DTO;

use App\Entity\Player;

class FilteredPlayerDTO
{
    public int $id;
    public string $name;
    public ?string $slug;
    public ?string $first_name;
    public ?string $second_name;
    public ?string $image;

    public function __construct(Player $player)
    {
        $this->id = $player->getId();
        $this->name = $player->getName();
        $this->slug = $player->getSlug();
        $this->first_name = $player->getFirstName();
        $this->second_name = $player->getLastName();
        $this->image = $player->getImage();
    }
}
