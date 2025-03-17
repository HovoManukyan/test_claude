<?php
namespace App\DTO;

use App\Entity\Team;
use App\Entity\Player;

class TeamSelectedFilterDTO
{
    public int $id;
    public string $pandascoreId;
    public string $name;
    public string $slug;


    public function __construct(Team $team, ?Player $excludePlayer = null)
    {
        $this->id = $team->getId();
        $this->pandascoreId = $team->getPandascoreId();
        $this->name = $team->getName();
        $this->slug = $team->getSlug();

    }
}
