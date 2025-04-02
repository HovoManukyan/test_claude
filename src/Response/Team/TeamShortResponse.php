<?php

declare(strict_types=1);

namespace App\Response\Team;

use App\Entity\Team;
use App\Response\Player\PlayerShortResponse;

final class TeamShortResponse
{
    /**
     * @param int $id Идентификатор команды
     * @param string $name Название команды
     * @param string $slug Slug команды
     * @param string|null $image Изображение
     * @param string|null $location Локация
     * @param array|null $players Игроки команды (кроме текущего игрока)
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $image = null,
        public readonly ?string $location = null,
        public readonly ?array $players = null,
    ) {
    }

    /**
     * Создает объект ответа из сущности
     *
     * @param Team $team Сущность команды
     * @param int|null $excludePlayerId ID игрока, которого нужно исключить из списка
     * @return self Объект ответа
     */
    public static function fromEntity(Team $team, ?int $excludePlayerId = null): self
    {
        // Преобразуем игроков в PlayerShortResponse, исключая указанного игрока
        $playerResponses = [];
        foreach ($team->getPlayers() as $player) {
            if ($excludePlayerId === null || $player->getId() !== $excludePlayerId) {
                $playerResponses[] = PlayerShortResponse::fromEntity($player);
            }
        }

        return new self(
            id: $team->getId(),
            name: $team->getName(),
            slug: $team->getSlug(),
            image: $team->getImage(),
            location: $team->getLocation(),
            players: !empty($playerResponses) ? $playerResponses : null,
        );
    }

    /**
     * Создает объект ответа из массива данных
     *
     * @param array $data Массив данных
     * @return self Объект ответа
     */
    public static function fromArray(array $data): self
    {
        // Преобразуем игроков в PlayerShortResponse, если они есть
        $playerResponses = null;
        if (isset($data['players']) && is_array($data['players']) && !empty($data['players'])) {
            $playerResponses = [];
            foreach ($data['players'] as $playerData) {
                $playerResponses[] = PlayerShortResponse::fromArray($playerData);
            }
        }

        return new self(
            id: $data['id'],
            name: $data['name'],
            slug: $data['slug'],
            image: $data['image'] ?? null,
            location: $data['location'] ?? null,
            players: !empty($playerResponses) ? $playerResponses : null,
        );
    }
}