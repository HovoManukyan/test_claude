<?php

declare(strict_types=1);

namespace App\Response;

use App\Entity\Team;
use App\Entity\Player;

class TeamResponse
{
    /**
     * @param int $id
     * @param string $pandascoreId
     * @param string $name
     * @param string $slug
     * @param string|null $image
     * @param string|null $location
     * @param array|null $stats
     * @param string|null $bestMap
     * @param array|null $lastGames
     * @param string|null $bio
     * @param array $socials
     * @param PlayerResponse[]|null $players
     * @param int|null $totalPrizepool
     */
    public function __construct(
        public readonly int     $id,
        public readonly string  $pandascoreId,
        public readonly string  $name,
        public readonly string  $slug,
        public readonly ?string $image,
        public readonly ?string $location,
        public readonly ?array  $stats,
        public readonly ?string $bestMap,
        public readonly ?array  $lastGames = null,
        public readonly ?string $bio = null,
        public readonly array   $socials = [],
        public readonly ?array  $players = null,
        public readonly ?int    $totalPrizepool = null,
    )
    {
    }

    /**
     * Create from a Team entity
     *
     * @param Team $team Team entity
     * @param Player|null $excludePlayer Optional player to exclude from team player list
     * @return self Response DTO
     */
    public static function fromEntity(Team $team, ?Player $excludePlayer = null): self
    {
        $players = null;

        // Map players if they exist
        if (!$team->getPlayers()->isEmpty()) {
            $playersList = $team->getPlayers()->toArray();

            // Filter out excluded player if provided
            if ($excludePlayer !== null) {
                $playersList = array_filter(
                    $playersList,
                    fn(Player $player) => $player->getId() !== $excludePlayer->getId()
                );
            }

            // Map to player responses
            $players = array_map(
                fn(Player $player) => PlayerShortResponse::fromEntity($player),
                array_values($playersList)
            );
        }

        return new self(
            id: $team->getId(),
            pandascoreId: $team->getPandascoreId(),
            name: $team->getName(),
            slug: $team->getSlug(),
            image: $team->getImage(),
            location: $team->getLocation(),
            stats: $team->getStats(),
            bestMap: $team->getBestMap(),
            lastGames: $team->getLastGames(),
            bio: $team->getBio(),
            socials: $team->getSocials(),
            players: $players,
            totalPrizepool: $team->getTotalPrizepool()
        );
    }
}