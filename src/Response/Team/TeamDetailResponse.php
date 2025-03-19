<?php

declare(strict_types=1);

namespace App\Response\Team;

use App\Entity\Banner;
use App\Entity\Team;
use App\Response\Banner\BannerResponse;

final class TeamDetailResponse
{
    /**
     * @param TeamResponse $team Данные команды
     * @param BannerResponse|null $banner Баннер страницы
     * @param TeamShortResponse[] $otherTeams Другие команды для рекомендаций
     */
    public function __construct(
        public readonly TeamResponse $team,
        public readonly ?BannerResponse $banner = null,
        public readonly array $otherTeams = [],
    ) {
    }

    /**
     * Создает объект ответа из сущностей
     *
     * @param Team $team Команда
     * @param Banner|null $banner Баннер
     * @param Team[] $otherTeams Другие команды
     */
    public static function fromEntities(Team $team, ?Banner $banner, array $otherTeams = []): self
    {
        // Преобразуем другие команды в TeamShortResponse
        $otherTeamResponses = [];
        foreach ($otherTeams as $otherTeam) {
            $otherTeamResponses[] = TeamShortResponse::fromEntity($otherTeam);
        }

        return new self(
            team: TeamResponse::fromEntity($team),
            banner: $banner ? BannerResponse::fromEntity($banner) : null,
            otherTeams: $otherTeamResponses,
        );
    }
}