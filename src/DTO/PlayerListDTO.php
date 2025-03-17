<?php
namespace App\DTO;

use App\Entity\Player;

class PlayerListDTO
{
    public int $id;
    public string $pandascoreId;
    public string $name;
    public ?string $firstName;
    public ?string $lastName;
    public ?string $nationality;
    public string $slug;
    public ?string $image;
    public ?string $birthday;
    public ?array $crosshair;
    public ?TeamDTO $currentTeam;
    public ?string $totalWon;
    public ?array $stats;

    public function __construct(Player $player)
    {
        $this->id = $player->getId();
        $this->pandascoreId = $player->getPandascoreId();
        $this->name = $player->getName();
        $this->firstName = $player->getFirstName();
        $this->lastName = $player->getLastName();
        $this->nationality = $player->getNationality();
        $this->slug = $player->getSlug();
        $this->image = $player->getImage();
        $this->birthday = $player->getBirthday()?->format('Y-m-d');
        $this->crosshair = $player->getCrosshair();
        $this->totalWon = $player->getTotalWon();
        $this->stats = $player->getStats();
        $this->currentTeam = $player->getCurrentTeam() ? new TeamDTO($player->getCurrentTeam(), $player) : null;
    }

}
