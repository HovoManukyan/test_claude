<?php

namespace App\DTO;

use App\Entity\Game;
use App\Entity\Team;
use App\Entity\Player;
use Doctrine\Common\Collections\Collection;

class TeamDTO
{
    public int $id;
    public string $pandascoreId;
    public string $name;
    public string $slug;
    public ?string $image;
    public ?string $bio;
    public array $socials;
    public ?string $location;
    public ?array $players;
    public ?array $stats;
    public ?string $best_map;
    public ?array $lastGames;
    public ?int $totalPrize;

    public function __construct(Team $team, ?Player $excludePlayer = null)
    {
        $this->id = $team->getId();
        $this->pandascoreId = $team->getPandascoreId();
        $this->name = $team->getName();
        $this->slug = $team->getSlug();
        $this->image = $team->getImage();
        $this->bio = $team->getBio();
        $this->socials = $team->getSocials();
        $this->location = $team->getLocation();
        $this->stats = $team->getStats();
        $this->best_map = $this->getBestMap($team);
        $this->lastGames = $this->getTeamLastGames($team);
        $this->totalPrize = $this->getTeamPrizepool($team);

        $this->players = array_values(array_filter(
            array_map(fn(Player $p) => new FilteredPlayerDTO($p), $team->getPlayers()->toArray()),
            fn(FilteredPlayerDTO $p) => $excludePlayer === null || $p->id !== $excludePlayer->getId()
        ));
    }

    function getBestMap(Team $team)
    {
        if (!$team->getStats()) {
            return null;
        }
        $stats = $team->getStats();
        $maps = $stats['maps'];
        if (empty($maps)) {
            return null;
        }
        $bestMap = null;
        $bestPercentage = 0;  // Инициализация максимального процента побед
        foreach ($maps as $map) {
            if ($map['total_played'] > 0) {  // Проверка, что общее количество игр больше 0, чтобы избежать деления на 0
                $percent = ($map['wins'] / $map['total_played']) * 100;

                if ($percent > $bestPercentage) {
                    $bestPercentage = $percent;  // Обновляем максимальный процент
                    $bestMap = $map;  // Обновляем лучшую карту
                }
            }
        }
        return $bestMap ? $bestMap['name'] : null;
    }

    function getTeamLastGames(Team $team): ?array
    {
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
                'begin_at' => $tournament->getBeginAt()->format('d.m.Y'),
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

            foreach ($round_data as $round) {
                if ($round['winner_team'] == $team->getPandascoreId()) {
                    $teamWins++;
                } else {
                    $opponentWins++;
                }
            }

            $teamFilteredGame['score'] = [
                'current_team' => $teamWins,
                'opponent' => $opponentWins
            ];
            foreach ($round_score as $score) {
                // Если это текущая команда
                if ($score['team_id'] == $team->getPandascoreId()) {
                    $teamFilteredGame['match']['current_team'] = [
                        'logo' => $team->getImage(),
                        'name' => $team->getName(),
                        'slug' => $team->getSlug(),
                        'score' => $score['score']
                    ];
                } else {
                    // Ищем команду оппонента по ID
                    $opponent = null;

                    // Проверяем, что $teams - это коллекция
                    if ($teams instanceof Collection) {
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
                            'score' => $score['score']
                        ];
                    } else {
                        // Если оппонент не найден, добавляем только счет
                        $teamFilteredGame['match']['opponent'] = [
                            'score' => $score['score']
                        ];
                    }
                }
            }

            if ($results) {
                foreach ($results as $result) {
                    if ($result['team_id'] == $team->getPandascoreId()) {
                        $teamFilteredGame['results']['current_team'] = $result['score'];
                    } else {
                        $teamFilteredGame['results']['opponent'] = $result['score'];
                    }
                }
            }


            $teamFilteredGames[] = $teamFilteredGame;
        }

        return $teamFilteredGames;
    }

    function getTeamPrizepool(Team $team)
    {
        $tournaments = $team->getTeamTournaments();
        $prizepool = 0;
        if ($tournaments->isEmpty()) {
            return $prizepool;
        }
        foreach ($tournaments as $tournament){
            $tournamentPrizepool = $tournament->getPrizepool();
            if ($tournamentPrizepool != 0 or $tournamentPrizepool != '') {
                $prizepool += $tournamentPrizepool;
            }
        }
        return $prizepool;
    }
}