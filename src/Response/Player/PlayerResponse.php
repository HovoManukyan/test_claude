<?php

declare(strict_types=1);

namespace App\Response\Player;

use App\Entity\Player;
use App\Response\Team\TeamShortResponse;

final class PlayerResponse
{
    /**
     * @param int $id Идентификатор игрока
     * @param string $name Имя игрока
     * @param string $slug Slug игрока
     * @param string|null $firstName Имя
     * @param string|null $lastName Фамилия
     * @param string|null $nationality Национальность
     * @param string|null $image Изображение
     * @param string|null $birthday Дата рождения
     * @param int|null $age Возраст
     * @param array|null $crosshair Настройки прицела
     * @param array $socials Социальные сети
     * @param string|null $bio Биография
     * @param TeamShortResponse|null $currentTeam Текущая команда
     * @param array|null $stats Статистика
     * @param array|null $lastGames Последние игры
     */
    public function __construct(
        public readonly int $id,
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
        public readonly ?TeamShortResponse $currentTeam = null,
        public readonly ?array $stats = null,
        public readonly ?array $lastGames = null,
    ) {
    }

    /**
     * Создает объект ответа из сущности
     *
     * @param Player $player Сущность игрока
     * @return self Объект ответа
     */
    public static function fromEntity(Player $player): self
    {
        return new self(
            id: $player->getId(),
            name: $player->getName(),
            slug: $player->getSlug(),
            firstName: $player->getFirstName(),
            lastName: $player->getLastName(),
            nationality: $player->getNationality(),
            image: $player->getImage(),
            birthday: $player->getBirthday()?->format('Y-m-d'),
            age: $player->getAge(),
            crosshair: $player->getCrosshair(),
            socials: $player->getSocials(),
            bio: $player->getBio(),
            currentTeam: $player->getCurrentTeam() ? TeamShortResponse::fromEntity($player->getCurrentTeam()) : null,
            stats: $player->getStats(),
            lastGames: $player->getLastGames(),
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
        // Проверяем и преобразуем birthday, если это объект DateTime
        $birthday = $data['birthday'] ?? null;
        if ($birthday instanceof \DateTime || $birthday instanceof \DateTimeImmutable) {
            $birthday = $birthday->format('Y-m-d');
        }

        // Создаем объект текущей команды, если она есть
        $currentTeam = null;
        if (isset($data['currentTeam'])) {
            $currentTeam = TeamShortResponse::fromArray($data['currentTeam']);
        }

        return new self(
            id: $data['id'],
            name: $data['name'],
            slug: $data['slug'],
            firstName: $data['firstName'] ?? null,
            lastName: $data['lastName'] ?? null,
            nationality: $data['nationality'] ?? null,
            image: $data['image'] ?? null,
            birthday: $birthday, // Теперь это гарантированно строка или null
            age: $data['age'] ?? null,
            crosshair: $data['crosshair'] ?? null,
            socials: $data['socials'] ?? [],
            bio: $data['bio'] ?? null,
            currentTeam: $currentTeam,
            stats: $data['stats'] ?? null,
            lastGames: $data['lastGames'] ?? null,
        );
    }
}