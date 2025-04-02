<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity]
#[ORM\Table(name: 'tournaments')]
class Tournament
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER, unique: true)]
    private int $tournamentId;

    #[ORM\Column(type: Types::STRING)]
    private string $name;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $beginAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\Column(type: Types::STRING, length: 2, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $detailedStats;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $hasBracket;

    #[ORM\Column(type: Types::INTEGER)]
    private int $leagueId;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $league = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $liveSupported;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $matches = null;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $expectedRoster = null;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $parsedTeams = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $prizepool = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $region = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $serieId;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $serie = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $tier = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $winnerId = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $winnerType = null;

    #[ORM\ManyToMany(targetEntity: Player::class, inversedBy: 'playerTournaments', cascade: ['persist'])]
    #[ORM\JoinTable(name: 'player_tournaments')]
    private Collection $players;

    #[ORM\ManyToMany(targetEntity: Team::class, inversedBy: 'teamTournaments', cascade: ['persist'])]
    #[ORM\JoinTable(name: 'team_tournaments')]
    private Collection $teams;

    #[ORM\OneToMany(targetEntity: Game::class, mappedBy: 'tournament')]
    private Collection $games;


    public function __construct()
    {
        $this->teams = new ArrayCollection();
        $this->players = new ArrayCollection();
        $this->games = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTournamentId(): int
    {
        return $this->tournamentId;
    }

    public function setTournamentId(int $tournamentId): self
    {
        $this->tournamentId = $tournamentId;
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getBeginAt(): ?\DateTimeImmutable
    {
        return $this->beginAt;
    }

    public function setBeginAt(?\DateTimeImmutable $beginAt): self
    {
        $this->beginAt = $beginAt;
        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(?\DateTimeImmutable $endAt): self
    {
        $this->endAt = $endAt;
        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;
        return $this;
    }

    public function hasDetailedStats(): bool
    {
        return $this->detailedStats;
    }

    public function setDetailedStats(bool $detailedStats): self
    {
        $this->detailedStats = $detailedStats;
        return $this;
    }

    public function hasBracket(): bool
    {
        return $this->hasBracket;
    }

    public function setHasBracket(bool $hasBracket): self
    {
        $this->hasBracket = $hasBracket;
        return $this;
    }

    public function getLeagueId(): int
    {
        return $this->leagueId;
    }

    public function setLeagueId(int $leagueId): self
    {
        $this->leagueId = $leagueId;
        return $this;
    }

    public function getLeague(): ?array
    {
        return $this->league;
    }

    public function setLeague(?array $league): self
    {
        $this->league = $league;
        return $this;
    }

    public function isLiveSupported(): bool
    {
        return $this->liveSupported;
    }

    public function setLiveSupported(bool $liveSupported): self
    {
        $this->liveSupported = $liveSupported;
        return $this;
    }

    public function getMatches(): ?array
    {
        return $this->matches;
    }

    public function setMatches(?array $matches): self
    {
        $this->matches = $matches;
        return $this;
    }

    public function getExpectedRoster(): ?array
    {
        return $this->expectedRoster;
    }

    public function setExpectedRoster(?array $expectedRoster): self
    {
        $this->expectedRoster = $expectedRoster;
        return $this;
    }

    public function getParsedTeams(): ?array
    {
        return $this->parsedTeams;
    }

    public function setParsedTeams(?array $parsedTeams): self
    {
        $this->parsedTeams = $parsedTeams;
        return $this;
    }

    public function getPrizepool(): ?string
    {
        return $this->prizepool;
    }

    public function setPrizepool(?string $prizepool): self
    {
        $this->prizepool = $prizepool;
        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): self
    {
        $this->region = $region;
        return $this;
    }

    public function getSerieId(): int
    {
        return $this->serieId;
    }

    public function setSerieId(int $serieId): self
    {
        $this->serieId = $serieId;
        return $this;
    }

    public function getSerie(): ?array
    {
        return $this->serie;
    }

    public function setSerie(?array $serie): self
    {
        $this->serie = $serie;
        return $this;
    }

    public function getTier(): ?string
    {
        return $this->tier;
    }

    public function setTier(?string $tier): self
    {
        $this->tier = $tier;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getWinnerId(): ?int
    {
        return $this->winnerId;
    }

    public function setWinnerId(?int $winnerId): self
    {
        $this->winnerId = $winnerId;
        return $this;
    }

    public function getWinnerType(): ?string
    {
        return $this->winnerType;
    }

    public function setWinnerType(?string $winnerType): self
    {
        $this->winnerType = $winnerType;
        return $this;
    }

    public function addPlayer(Player $player): self
    {
        if (!$this->players->exists(fn($key, $p) => $p->getPandascoreId() === $player->getPandascoreId())) {
            $this->players->add($player);
        }
        return $this;
    }

    public function getPlayers(): Collection
    {
        return $this->players;
    }


    public function addTeam(Team $team): self
    {
        if (!$this->teams->exists(fn($key, $t) => $t->getId() === $team->getId())) {
            $this->teams->add($team);
        }

        return $this;
    }


    public function getTeams(): Collection
    {
        return $this->teams;
    }

    public function getGames(): Collection
    {
        return $this->games;
    }

    public function addGame(Game $game): self
    {
        if (!$this->games->contains($game)) {
            $this->games[] = $game;
            $game->setTournament($this);
        }

        return $this;
    }

    public function removeGame(Game $game): self
    {
        if ($this->games->removeElement($game)) {
            // установить null, если ($game->getTournament() === $this)
            if ($game->getTournament() === $this) {
                $game->setTournament(null);
            }
        }

        return $this;
    }

}
