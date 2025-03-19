<?php

declare(strict_types=1);

namespace App\Response\Player;

use App\Entity\Player;
use App\Response\Team\TeamShortResponse;
use Doctrine\Common\Collections\Collection;

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
     * Обрабатывает последние игры игрока
     */
    private static function getPlayerLastGames(Player $player): ?array
    {
        $team = $player->getCurrentTeam();
        if (!$team) {
            return null;
        }

        // Получаем все игры команды
        $teamAllGames = $team->getGames();
        $teamLastGames = $team->getLastGames();

        // Собираем ID последних игр
        $lastGameIds = [];
        if (is_array($teamLastGames)) {
            foreach ($teamLastGames as $gameData) {
                if (isset($gameData['id'])) {
                    $lastGameIds[] = (string)$gameData['id'];
                }
            }
        }

        // Фильтруем игры по ID из последних игр
        $filteredGames = $teamAllGames->filter(function (Game $game) use ($lastGameIds) {
            return in_array($game->getPandascoreId(), $lastGameIds);
        });

        // Проверяем, есть ли отфильтрованные игры
        if ($filteredGames->isEmpty()) {
            return null;
        }

        $teamFilteredGames = [];
        foreach ($filteredGames as $filteredGame) {
            // Получаем турнир
            $tournament = $filteredGame->getTournament();
            if (!$tournament) {
                continue;
            }

            $teams = $filteredGame->getTeams();

            $teamFilteredGame = [
                'tournamentName' => $tournament->getName(),
                'tournamentSlug' => $tournament->getSlug(),
                'current_team_name' => $team->getName(),
                'opponent_team_name' => 'T',
                'begin_at' => $tournament->getBeginAt()?->format('d.m.Y'),
                'map' => $filteredGame->getMap(),
                'match' => [],
                'results' => [
                    'current_team' => 0,
                    'opponent' => 0
                ]
            ];

            $round_score = $filteredGame->getRoundsScore();
            $round_data = $filteredGame->getRounds();
            $results = $filteredGame->getResults();
            $teamWins = 0;
            $opponentWins = 0;

            if (is_array($round_data)) {
                foreach ($round_data as $round) {
                    if (isset($round['winner_team']) && $round['winner_team'] == $team->getPandascoreId()) {
                        $teamWins++;
                    } else {
                        $opponentWins++;
                    }
                }
            }

            $teamFilteredGame['score'] = [
                'current_team' => $teamWins,
                'opponent' => $opponentWins
            ];

            if (is_array($round_score)) {
                foreach ($round_score as $score) {
                    // Если это текущая команда
                    if (isset($score['team_id']) && $score['team_id'] == $team->getPandascoreId()) {
                        $teamFilteredGame['match']['current_team'] = [
                            'logo' => $team->getImage(),
                            'name' => $team->getName(),
                            'slug' => $team->getSlug(),
                            'score' => $score['score'] ?? 0
                        ];
                    } else {
                        // Ищем команду оппонента по ID
                        $opponent = null;

                        // Проверяем, что $teams - это коллекция
                        if ($teams instanceof Collection && isset($score['team_id'])) {
                            $opponent = $teams->filter(function ($teamObj) use ($score) {
                                return $teamObj->getPandascoreId() == $score['team_id'];
                            })->first();
                        }

                        // Если оппонент найден
                        if ($opponent) {
                            $teamFilteredGame['opponent_team_name'] = $opponent->getName();
                            $teamFilteredGame['match']['opponent'] = [
                                'logo' => $opponent->getImage(),
                                'name' => $opponent->getName(),
                                'slug' => $opponent->getSlug(),
                                'score' => $score['score'] ?? 0
                            ];
                        } else {
                            // Если оппонент не найден, добавляем только счет
                            $teamFilteredGame['match']['opponent'] = [
                                'score' => $score['score'] ?? 0
                            ];
                        }
                    }
                }
            }

            if (is_array($results)) {
                foreach ($results as $result) {
                    if (isset($result['team_id'])) {
                        if ($result['team_id'] == $team->getPandascoreId()) {
                            $teamFilteredGame['results']['current_team'] = $result['score'] ?? 0;
                        } else {
                            $teamFilteredGame['results']['opponent'] = $result['score'] ?? 0;
                        }
                    }
                }
            }

            $teamFilteredGames[] = $teamFilteredGame;
        }

        return $teamFilteredGames;
    }
}