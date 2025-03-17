<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;

#[ORM\Entity]
class Game
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    #[Groups([
        "game:list",
        "game:details",
        "team:details:game"
    ])]
    private int $id;

    #[ORM\Column(type: "string", length: 36, unique: true)]
    #[Groups([
        "game:list",
        "game:details",
        "team:details:game"
    ])]
    private string $pandascore_id;

    #[ORM\Column(type: "string", length: 255)]
    #[Groups([
        "game:list",
        "game:details",
        "team:details:game"
    ])]
    private string $name;

    #[ORM\Column(type: "json", nullable: true, options: ["jsonb" => true])]
    #[Groups([
        "game:details"
    ])]
    private ?array $match = null;

    #[ORM\Column(type: "json", nullable: true, options: ["jsonb" => true])]
    #[Groups([
        "game:details"
    ])]
    private ?array $map = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    #[Groups([
        "game:list",
        "game:details",
        "team:details:game"
    ])]
    private ?\DateTimeInterface $begin_at = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    #[Groups([
        "game:list",
        "game:details",
        "team:details:game"
    ])]
    private ?\DateTimeInterface $end_at = null;

    #[ORM\Column(type: "json", nullable: true, options: ["jsonb" => true])]
    #[Groups([
        "game:details"
    ])]
    private ?array $winner = null;

    #[ORM\Column(type: "json", nullable: true, options: ["jsonb" => true])]
    #[Groups([
        "game:details"
    ])]
    private ?array $rounds = null;

    #[ORM\Column(type: "json", nullable: true, options: ["jsonb" => true])]
    #[Groups([
        "game:list",
        "game:details",
        "team:details:game"
    ])]
    private ?array $rounds_score = null;

    #[ORM\Column(type: "string", length: 50)]
    #[Groups([
        "game:list",
        "game:details",
        "team:details:game"
    ])]
    private string $status = 'unknown';

    #[ORM\Column(type: "json", nullable: true, options: ["jsonb" => true])]
    #[Groups([
        "game:details"
    ])]
    private ?array $results = null;

    #[ORM\Column(type: "json", nullable: true, options: ["jsonb" => true])]
    #[Groups([
        "game:details"
    ])]
    private ?array $data = null;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $created_at;


    #[ORM\ManyToMany(targetEntity: Team::class, inversedBy: "games")]
    #[ORM\JoinTable(name: "team_game")]
    #[Groups([
        "game:details"
    ])]
    #[MaxDepth(1)]
    private Collection $teams;

    #[ORM\ManyToMany(targetEntity: Player::class, inversedBy: "games")]
    #[ORM\JoinTable(name: "player_game")]
    #[Groups([
        "game:details"
    ])]
    #[MaxDepth(1)]
    private Collection $players;

    #[ORM\ManyToOne(targetEntity: Tournament::class, inversedBy: "games")]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups([
        "game:list",
        "game:details"
    ])]
    private ?Tournament $tournament = null;

    public function __construct()
    {
        $this->teams = new ArrayCollection();
        $this->players = new ArrayCollection();
        $this->created_at = new \DateTime();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPandascoreId(): string
    {
        return $this->pandascore_id;
    }

    public function setPandascoreId(string $pandascore_id): self
    {
        $this->pandascore_id = $pandascore_id;
        return $this;
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

    public function getMatch(): ?array
    {
        return $this->match;
    }

    public function setMatch(?array $match): self
    {
        $this->match = $match;
        return $this;
    }

    public function getMap(): ?array
    {
        return $this->map;
    }

    public function setMap(?array $map): self
    {
        $this->map = $map;
        return $this;
    }

    public function getBeginAt(): ?\DateTimeInterface
    {
        return $this->begin_at;
    }

    public function setBeginAt(?\DateTimeInterface $begin_at): self
    {
        $this->begin_at = $begin_at;
        return $this;
    }

    public function getEndAt(): ?\DateTimeInterface
    {
        return $this->end_at;
    }

    public function setEndAt(?\DateTimeInterface $end_at): self
    {
        $this->end_at = $end_at;
        return $this;
    }

    public function getWinner(): ?array
    {
        return $this->winner;
    }

    public function setWinner(?array $winner): self
    {
        $this->winner = $winner;
        return $this;
    }

    public function getRounds(): ?array
    {
        return $this->rounds;
    }

    public function setRounds(?array $rounds): self
    {
        $this->rounds = $rounds;
        return $this;
    }

    public function getRoundsScore(): ?array
    {
        return $this->rounds_score;
    }

    public function setRoundsScore(?array $rounds_score): self
    {
        $this->rounds_score = $rounds_score;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getTeams(): Collection
    {
        return $this->teams;
    }

    public function addTeam(Team $team, bool $updateTeam = true): self
    {
        if (!$this->teams->contains($team)) {
            $this->teams[] = $team;

            if ($updateTeam) {
                $team->addGame($this, false);
            }
        }

        return $this;
    }

    public function removeTeam(Team $team, bool $updateTeam = true): self
    {
        if ($this->teams->removeElement($team) && $updateTeam) {
            $team->removeGame($this, false);
        }

        return $this;
    }

    public function getPlayers(): Collection
    {
        return $this->players;
    }

    public function addPlayer(Player $player, bool $updatePlayer = true): self
    {
        if (!$this->players->contains($player)) {
            $this->players[] = $player;

            if ($updatePlayer) {
                $player->addGame($this, false);
            }
        }

        return $this;
    }

    public function removePlayer(Player $player, bool $updatePlayer = true): self
    {
        if ($this->players->removeElement($player) && $updatePlayer) {
            $player->removeGame($this, false);
        }

        return $this;
    }


    public function getTournament(): ?Tournament
    {
        return $this->tournament;
    }

    public function setTournament(?Tournament $tournament): self
    {
        $this->tournament = $tournament;
        return $this;
    }

    public function getResults(): ?array
    {
        return $this->results;
    }

    public function setResults(?array $results): void
    {
        $this->results = $results;
    }

}