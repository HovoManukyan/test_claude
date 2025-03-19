<?php

declare(strict_types=1);

namespace App\Response\Team;

use App\Entity\Banner;
use App\Entity\Team;
use App\Response\Banner\BannerResponse;
use Doctrine\ORM\Tools\Pagination\Paginator;

final class TeamListResponse
{
    /**
     * @param TeamShortResponse[] $teams Список команд
     * @param array $meta Метаданные пагинации (total, page, limit, pages)
     * @param BannerResponse|null $banner Баннер для страницы
     */
    public function __construct(
        public readonly array $teams,
        public readonly array $meta,
        public readonly ?BannerResponse $banner = null,
    ) {
    }

    /**
     * Создает объект ответа из пагинатора и баннера
     *
     * @param Paginator $paginator Пагинатор команд
     * @param int $page Текущая страница
     * @param int $limit Количество элементов на странице
     * @param Banner|null $banner Баннер
     */
    public static function fromPaginator(
        Paginator $paginator,
        int $page,
        int $limit,
        ?Banner $banner = null
    ): self {
        // Преобразуем команды в TeamShortResponse
        $teamResponses = [];
        foreach ($paginator as $team) {
            $teamResponses[] = TeamShortResponse::fromEntity($team);
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

        return new self(
            teams: $teamResponses,
            meta: $meta,
            banner: $banner ? BannerResponse::fromEntity($banner) : null,
        );
    }
}