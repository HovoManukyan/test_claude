<?php

namespace App\Entity;

use App\Repository\TeamRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Ignore;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
#[ORM\Table(name: "team")]
#[ORM\HasLifecycleCallbacks]
class Team extends AbstractEntity
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
    private string $pandascoreId;

    #[ORM\Column(type: "string", length: 255)]
    #[Assert\NotBlank]
    #[Groups([
        "team:list",
        "team:details",
        "player:details:team",
        "game:details:team"
    ])]
    private string $name;

    #[ORM\Column(type: "string", length: 255, unique: true)]
    #[Assert\NotBlank]
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
    private ?string $acronym = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    #[Groups([
        "team:list",
        "team:details",
        "player:details:team",
        "game:details:team"
    ])]
    private ?string $image = null;

    #[ORM\Column(type: "text", nullable: true)]
    #[Groups([
        "team:details",
        "team:edit"
    ])]
    private ?string $bio = null;

    #[ORM\Column(type: "json", options: ["jsonb" => true])]
    #[Assert\Type("array")]
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
    private ?string $location = null;

    #[ORM\OneToMany(targetEntity: Player::class, mappedBy: "currentTeam")]
    #[Groups([
        "team:details"
    ])]
    #[MaxDepth(1)]
    private Collection $players;

    #[ORM\ManyToMany(targetEntity: Tournament::class, mappedBy: 'teams', cascade: ['persist'])]
    private Collection $teamTournaments;

    #[ORM\Column(type: "json", nullable: true, options: ["jsonb" => true])]
    private ?array $stats = null;

    #[ORM\Column(type: "json", nullable: true, options: ["jsonb" => true])]
    private ?array $lastGames = null;

    #[ORM\ManyToMany(targetEntity: Game::class, mappedBy: 'teams')]
    #[Groups([
        "team:details"
    ])]
    #[MaxDepth(1)]
    private Collection $games;

    #[ORM\Column(type: "datetime_immutable")]
    protected DateTimeImmutable $createdAt;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    protected ?DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->players = new ArrayCollection();
        $this->teamTournaments = new ArrayCollection();
        $this->games = new ArrayCollection();
        $this->socials = [];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPandascoreId(): string
    {
        return $this->pandascoreId;
    }

    public function setPandascoreId(string $pandascoreId): self
    {
        $this->pandascoreId = $pandascoreId;
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

    public function setAcronym(?string $acronym): self
    {
        $this->acronym = $acronym;
        return $this;
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

    public function setStats(?array $stats): self
    {
        $this->stats = $stats;
        return $this;
    }

    public function getLastGames(): ?array
    {
        return $this->lastGames;
    }

    public function setLastGames(?array $lastGames): self
    {
        $this->lastGames = $lastGames;
        return $this;
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

    /**
     * Get the best map for this team based on win percentage
     *
     * @Groups({"team:list", "team:details"})
     */
    public function getBestMap(): ?string
    {
        if (!$this->stats || empty($this->stats['maps'])) {
            return null;
        }

        $maps = $this->stats['maps'];
        $bestMap = null;
        $bestPercentage = 0;

        foreach ($maps as $map) {
            if ($map['total_played'] > 0) {
                $percent = ($map['wins'] / $map['total_played']) * 100;

                if ($percent > $bestPercentage) {
                    $bestPercentage = $percent;
                    $bestMap = $map;
                }
            }
        }

        return $bestMap ? $bestMap['name'] : null;
    }

    /**
     * Get the total prize pool won by this team
     *
     * @Groups({"team:details"})
     */
    public function getTotalPrizepool(): ?int
    {
        $prizepool = 0;

        if ($this->teamTournaments->isEmpty()) {
            return $prizepool;
        }

        foreach ($this->teamTournaments as $tournament) {
            $tournamentPrizepool = $tournament->getPrizepool();
            if ($tournamentPrizepool) {
                $prizepool += (int)$tournamentPrizepool;
            }
        }

        return $prizepool > 0 ? $prizepool : null;
    }
}