<?php

declare(strict_types=1);

namespace App\Response\Player;

use App\Entity\Banner;
use App\Entity\Player;
use App\Entity\Team;
use App\Response\Banner\BannerResponse;
use App\Response\Team\TeamShortResponse;
use Doctrine\ORM\Tools\Pagination\Paginator;

final class PlayerListResponse
{
    /**
     * @param PlayerResponse[] $players Список игроков
     * @param array $meta Метаданные пагинации (total, page, limit, pages)
     * @param BannerResponse|null $banner Баннер для страницы
     * @param TeamShortResponse[]|null $selectedTeams Выбранные команды для фильтрации
     */
    public function __construct(
        public readonly array $players,
        public readonly array $meta,
        public readonly ?BannerResponse $banner = null,
        public readonly ?array $selectedTeams = null,
    ) {
    }

    /**
     * Создает объект ответа из пагинатора и других сущностей
     *
     * @param Paginator $paginator Пагинатор игроков
     * @param int $page Текущая страница
     * @param int $limit Количество элементов на странице
     * @param Banner|null $banner Баннер
     * @param array|null $selectedTeams Выбранные команды
     */
    public static function fromPaginator(
        Paginator $paginator,
        int $page,
        int $limit,
        ?Banner $banner = null,
        ?array $selectedTeams = null
    ): self {
        // Преобразуем игроков в PlayerResponse
        $playerResponses = [];
        foreach ($paginator as $player) {
            $playerResponses[] = PlayerResponse::fromEntity($player);
        }

        // Количество страниц и общее количество элементов
        $total = $paginator->count();
        $pages = $limit > 0 ? (int)ceil($total / $limit) : 1;

        // Метаданные пагинации
        $meta = [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => $pages,
        ];

        // Преобразуем выбранные команды в TeamShortResponse
        $teamResponses = null;
        if ($selectedTeams !== null) {
            $teamResponses = [];
            foreach ($selectedTeams as $team) {
                $teamResponses[] = TeamShortResponse::fromEntity($team);
            }
        }

        return new self(
            players: $playerResponses,
            meta: $meta,
            banner: $banner ? BannerResponse::fromEntity($banner) : null,
            selectedTeams: $teamResponses,
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
        $players = [];
        foreach ($data['paginatedPlayers']['items'] as $playerData) {
            $players[] = PlayerResponse::fromArray($playerData);
        }

        $selectedTeams = null;
        if (isset($data['teams']) && is_array($data['teams'])) {
            $selectedTeams = [];
            foreach ($data['teams'] as $teamData) {
                $selectedTeams[] = TeamShortResponse::fromArray($teamData);
            }
        }

        return new self(
            players: $players,
            meta: $data['paginatedPlayers']['meta'],
            banner: isset($data['banner']) ? BannerResponse::fromArray($data['banner']) : null,
            selectedTeams: $selectedTeams,
        );
    }
}