<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
class Player
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', length: 11, unique: true)]
    #[Groups(['player:detail', 'player:list', 'team:detail'])]
    private int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    #[Groups(['player:detail', 'player:list', 'team:detail'])]
    private string $pandascoreId;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['player:detail', 'player:list', 'team:detail'])]
    private string $name;

    #[ORM\ManyToOne(targetEntity: Team::class, inversedBy: 'players')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['player:detail', 'player:list'])]
    private ?Team $currentTeam = null;

    #[ORM\ManyToMany(targetEntity: Team::class)]
    #[ORM\JoinTable(name: 'player_teams')]
    #[Groups(['player:detail'])]
    private Collection $teams;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['player:detail', 'player:list', 'team:detail'])]
    private ?string $firstName;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['player:detail', 'player:list', 'team:detail'])]
    private ?string $lastName;

    #[ORM\Column(type: 'string', length: 5, nullable: true)]
    #[Groups(['player:detail', 'player:list', 'team:detail'])]
    private ?string $nationality = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Groups(['player:detail', 'player:list', 'team:detail'])]
    private string $slug;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['player:detail', 'player:list', 'team:detail'])]
    private ?string $image;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['player:detail'])]
    private ?\DateTimeImmutable $birthday = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['player:detail', 'team:detail'])]
    private ?int $age = null;

    #[ORM\Column(type: 'json', nullable: true, options: ['jsonb' => true])]
    #[Groups(['player:detail', 'player:list', 'team:detail'])]
    private ?array $crosshair = null;

    #[ORM\Column(type: 'json', nullable: false, options: ['jsonb' => true])]
    #[Groups(['player:detail'])]
    private array $socials = [];

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['player:detail'])]
    private ?string $bio = null;

    #[ORM\ManyToMany(targetEntity: Skin::class)]
    #[ORM\JoinTable(name: 'player_skins')]
    #[Groups(['player:detail'])]
    private Collection $skins;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4, nullable: true)]
    #[Groups(['player:detail', 'player:list'])]
    private ?string $totalWon = null;

    #[ORM\Column(type: 'json', nullable: true, options: ['jsonb' => true])]
    #[Groups(['player:detail', 'team:detail'])]
    private ?array $stats = null;

    #[ORM\ManyToMany(targetEntity: Tournament::class, mappedBy: 'players', cascade: ['persist'])]
    private Collection $playerTournaments;

    #[ORM\Column(type: 'json', nullable: true, options: ['jsonb' => true])]
    private ?array $lastGames = null;

    #[ORM\ManyToMany(targetEntity: Game::class, mappedBy: 'players')]
    private Collection $games;

    public function __construct()
    {
        $this->teams = new ArrayCollection();
        $this->skins = new ArrayCollection();
        $this->games = new ArrayCollection();
        $this->playerTournaments = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }


    public function getPandascoreId(): string
    {
        return $this->pandascoreId;
    }

    public function setPandascoreId(string $pandascoreId): void
    {
        $this->pandascoreId = $pandascoreId;
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

    public function getCurrentTeam(): ?Team
    {
        return $this->currentTeam;
    }

    public function setCurrentTeam(?Team $currentTeam): self
    {
        $this->currentTeam = $currentTeam;
        return $this;
    }

    public function getTeams(): Collection
    {
        return $this->teams;
    }

    public function addTeam(Team $team): self
    {
        if (!$this->teams->contains($team)) {
            $this->teams[] = $team;
        }

        return $this;
    }

    public function removeTeam(Team $team): self
    {
        $this->teams->removeElement($team);
        return $this;
    }

    public function getSkins(): Collection
    {
        return $this->skins;
    }

    public function addSkin(Skin $skin): self
    {
        if (!$this->skins->contains($skin)) {
            $this->skins[] = $skin;
        }

        return $this;
    }

    public function removeSkin(Skin $skin): self
    {
        $this->skins->removeElement($skin);
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getNationality(): ?string
    {
        return $this->nationality;
    }

    public function setNationality(?string $nationality): void
    {
        $this->nationality = $nationality;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): void
    {
        $this->image = $image;
    }

    public function getBirthday(): ?\DateTimeImmutable
    {
        return $this->birthday;
    }

    public function setBirthday(?\DateTimeImmutable $birthday): void
    {
        $this->birthday = $birthday;
    }

    public function getCrosshair(): ?array
    {
        return $this->crosshair;
    }

    public function setCrosshair(?array $crosshair): void
    {
        $this->crosshair = $crosshair;
    }

    public function getSocials(): array
    {
        return $this->socials;
    }

    public function setSocials(array $socials): void
    {
        $this->socials = $socials;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): void
    {
        $this->bio = $bio;
    }

    public function getTotalWon(): ?string
    {
        return $this->totalWon;
    }

    public function setTotalWon(?string $totalWon): void
    {
        $this->totalWon = $totalWon;
    }

    public function getStats(): ?array
    {
        return $this->stats;
    }

    public function setStats(?array $stats): void
    {
        $this->stats = $stats;
    }

    public function getPlayerTournaments(): Collection
    {
        return $this->playerTournaments;
    }

    public function addTournament(Tournament $tournament): self
    {
        if (!$this->playerTournaments->contains($tournament)) {
            $this->playerTournaments->add($tournament);
//            $tournament->addPlayer($this);
        }
        return $this;
    }

    public function removeTournament(Tournament $tournament): self
    {
        $this->playerTournaments->removeElement($tournament);
        return $this;
    }

    public function getLastGames(): ?array
    {
        return $this->lastGames;
    }

    public function setLastGames(?array $lastGames): void
    {
        $this->lastGames = $lastGames;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(?int $age): void
    {
        $this->age = $age;
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
                $game->addPlayer($this, false);
            }
        }

        return $this;
    }

    public function removeGame(Game $game, bool $updateGame = true): self
    {
        if ($this->games->removeElement($game) && $updateGame) {
            $game->removePlayer($this, false);
        }

        return $this;
    }
}
