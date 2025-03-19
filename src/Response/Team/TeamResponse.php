<?php

declare(strict_types=1);

namespace App\Response\Team;

use App\Entity\Team;
use App\Response\Player\PlayerShortResponse;

final class TeamResponse
{
    /**
     * @param int $id Идентификатор команды
     * @param string $name Название команды
     * @param string $slug Slug команды
     * @param string|null $image Изображение
     * @param string|null $location Локация
     * @param string|null $bio Биография
     * @param array $socials Социальные сети
     * @param array|null $stats Статистика
     * @param string|null $bestMap Лучшая карта
     * @param array|null $lastGames Последние игры
     * @param PlayerShortResponse[] $players Игроки команды
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $image = null,
        public readonly ?string $location = null,
        public readonly ?string $bio = null,
        public readonly array $socials = [],
        public readonly ?array $stats = null,
        public readonly ?string $bestMap = null,
        public readonly ?array $lastGames = null,
        public readonly array $players = [],
    ) {
    }

    /**
     * Создает объект ответа из сущности
     */
    public static function fromEntity(Team $team): self
    {
        // Преобразуем игроков в PlayerShortResponse
        $playerResponses = [];
        foreach ($team->getPlayers() as $player) {
            $playerResponses[] = PlayerShortResponse::fromEntity($player);
        }

        return new self(
            id: $team->getId(),
            name: $team->getName(),
            slug: $team->getSlug(),
            image: $team->getImage(),
            location: $team->getLocation(),
            bio: $team->getBio(),
            socials: $team->getSocials(),
            stats: $team->getStats(),
            bestMap: $team->getBestMap(),
            lastGames: $team->getLastGames(),
            players: $playerResponses,
        );
    }
}