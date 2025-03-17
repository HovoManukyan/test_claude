<?php

namespace App\Entity;

use App\Repository\TeamRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
class Team
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    #[Groups([
        "team:list",
        "team:details",
        "player:details:team",
        "game:details:team"
    ])]
    private int $id;

    #[ORM\Column(type: "string", length: 36, unique: true)]
    #[Groups([
        "team:list",
        "team:details",
        "player:details:team",
        "game:details:team"
    ])]
    private string $pandascore_id;

    #[ORM\Column(type: "string", length: 255)]
    #[Groups([
        "team:list",
        "team:details",
        "player:details:team",
        "game:details:team"
    ])]
    private string $name;

    #[ORM\Column(type: "string", length: 255, unique: true)]
    #[Groups([
        "team:list",
        "team:details",
        "player:details:team",
        "game:details:team"
    ])]
    private string $slug;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    #[Groups([
        "team:list",
        "team:details",
        "player:details:team",
        "game:details:team"
    ])]
    private ?string $acronym;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    #[Groups([
        "team:list",
        "team:details",
        "player:details:team",
        "game:details:team"
    ])]
    private ?string $image;

    #[ORM\Column(type: "text", nullable: true)]
    #[Groups([
        "team:details",
        "team:edit"
    ])]
    private ?string $bio = null;

    #[ORM\Column(type: "json", options: ["jsonb" => true])]
    #[Groups([
        "team:details",
        "team:edit"
    ])]
    private array $socials = [];

    #[ORM\Column(type: "string", length: 5, nullable: true)]
    #[Groups([
        "team:list",
        "team:details",
        "player:details:team",
        "game:details:team"
    ])]
    private ?string $location;

    #[ORM\OneToMany(targetEntity: Player::class, mappedBy: "currentTeam")]
    #[Groups([
        "team:details"
    ])]
    #[MaxDepth(1)]
    private Collection $players;

    #[ORM\ManyToMany(targetEntity: Tournament::class, mappedBy: 'teams', cascade: ['persist'], fetch: 'EAGER')]
    private Collection $teamTournaments;

    #[ORM\Column(type: "json", nullable: true, options: ["jsonb" => true])]
    private ?array $stats;

    #[ORM\Column(type: "json", nullable: true, options: ["jsonb" => true])]
    private ?array $lastGames;

    #[ORM\ManyToMany(targetEntity: Game::class, mappedBy: 'teams')]
    #[Groups([
        "team:details"
    ])]
    #[MaxDepth(1)]
    private Collection $games;

    public function __construct()
    {
        $this->players = new ArrayCollection();
        $this->teamTournaments = new ArrayCollection();
        $this->games = new ArrayCollection();
    }

    // Getters and setters
    public function getId(): int
    {
        return $this->id;
    }

    public function getPandascoreId(): string
    {
        return $this->pandascore_id;
    }

    public function setPandascoreId(string $pandascore_id): void
    {
        $this->pandascore_id = $pandascore_id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getAcronym(): ?string
    {
        return $this->acronym;
    }

    public function setAcronym(?string $acronym): void
    {
        $this->acronym = $acronym;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;
        return $this;
    }

    public function getSocials(): array
    {
        return $this->socials;
    }

    public function setSocials(array $socials): self
    {
        $this->socials = $socials;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function getPlayers(): Collection
    {
        return $this->players;
    }

    public function addPlayer(Player $player): self
    {
        if (!$this->players->contains($player)) {
            $this->players[] = $player;
            $player->setCurrentTeam($this);
        }

        return $this;
    }

    public function removePlayer(Player $player): self
    {
        if ($this->players->removeElement($player)) {
            if ($player->getCurrentTeam() === $this) {
                $player->setCurrentTeam(null);
            }
        }

        return $this;
    }

    public function getTeamTournaments(): Collection
    {
        return $this->teamTournaments;
    }

    public function addTournament(Tournament $tournament, bool $updateTournament = true): self
    {
        if (!$this->teamTournaments->contains($tournament)) {
            $this->teamTournaments->add($tournament);

            if ($updateTournament) {
                $tournament->addTeam($this, false);
            }
        }

        return $this;
    }

    public function removeTournament(Tournament $tournament): self
    {
        $this->teamTournaments->removeElement($tournament);
        return $this;
    }

    public function getStats(): ?array
    {
        return $this->stats;
    }

    public function setStats(?array $stats): void
    {
        $this->stats = $stats;
    }

    public function getLastGames(): ?array
    {
        return $this->lastGames;
    }

    public function setLastGames(?array $lastGames): void
    {
        $this->lastGames = $lastGames;
    }

    public function getGames(): Collection
    {
        return $this->games;
    }

    public function addGame(Game $game, bool $updateGame = true): self
    {
        if (!$this->games->contains($game)) {
            $this->games[] = $game;

            if ($updateGame) {
                $game->addTeam($this, false);
            }
        }

        return $this;
    }

    public function removeGame(Game $game, bool $updateGame = true): self
    {
        if ($this->games->removeElement($game) && $updateGame) {
            $game->removeTeam($this, false);
        }

        return $this;
    }
}