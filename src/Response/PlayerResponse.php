<?php

declare(strict_types=1);

namespace App\Response;

use App\Entity\Player;
use App\Entity\Skin;
use App\Entity\Team;
use DateTimeInterface;

class PlayerResponse
{
    /**
     * @param int $id Player ID
     * @param string $pandascoreId PandaScore ID
     * @param string $name Player name
     * @param string $slug Player slug
     * @param string|null $firstName First name
     * @param string|null $lastName Last name
     * @param string|null $nationality Nationality code
     * @param string|null $image Player image URL
     * @param string|null $birthday Birthday in ISO format
     * @param int|null $age Player age
     * @param array|null $crosshair Crosshair settings
     * @param array $socials Social media links
     * @param string|null $bio Player biography
     * @param string|null $totalWon Total winnings
     * @param array|null $stats Player statistics
     * @param array|null $lastGames Last games data
     * @param TeamResponse|null $currentTeam Current team
     * @param TeamShortResponse[]|null $teams All teams
     * @param SkinResponse[]|null $skins Player skins
     */
    public function __construct(
        public readonly int $id,
        public readonly string $pandascoreId,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $nationality = null,
        public readonly ?string $image = null,
        public readonly ?string $birthday = null,
        public readonly ?int $age = null,
        public readonly ?array $crosshair = null,
        public readonly array $socials = [],
        public readonly ?string $bio = null,
        public readonly ?string $totalWon = null,
        public readonly ?array $stats = null,
        public readonly ?array $lastGames = null,
        public readonly ?TeamResponse $currentTeam = null,
        public readonly ?array $teams = null,
        public readonly ?array $skins = null,
    ) {
    }

    /**
     * Create from a Player entity
     *
     * @param Player $player Player entity
     * @return self Response DTO
     */
    public static function fromEntity(Player $player): self
    {
        // Map current team if it exists
        $currentTeam = null;
        if ($player->getCurrentTeam() !== null) {
            $currentTeam = TeamResponse::fromEntity($player->getCurrentTeam(), $player);
        }

        // Map teams if they exist
        $teams = null;
        if (!$player->getTeams()->isEmpty()) {
            $teams = array_map(
                fn(Team $team) => TeamShortResponse::fromEntity($team),
                $player->getTeams()->toArray()
            );
        }

        // Map skins if they exist
        $skins = null;
        if (!$player->getSkins()->isEmpty()) {
            $skins = array_map(
                fn(Skin $skin) => SkinResponse::fromEntity($skin),
                $player->getSkins()->toArray()
            );
        }

        // Format birthday
        $birthday = null;
        if ($player->getBirthday() instanceof DateTimeInterface) {
            $birthday = $player->getBirthday()->format('Y-m-d');
        }

        return new self(
            id: $player->getId(),
            pandascoreId: $player->getPandascoreId(),
            name: $player->getName(),
            slug: $player->getSlug(),
            firstName: $player->getFirstName(),
            lastName: $player->getLastName(),
            nationality: $player->getNationality(),
            image: $player->getImage(),
            birthday: $birthday,
            age: $player->getAge(),
            crosshair: $player->getCrosshair(),
            socials: $player->getSocials(),
            bio: $player->getBio(),
            totalWon: $player->getTotalWon(),
            stats: $player->getStats(),
            lastGames: $player->getLastGames(),
            currentTeam: $currentTeam,
            teams: $teams,
            skins: $skins
        );
    }
}