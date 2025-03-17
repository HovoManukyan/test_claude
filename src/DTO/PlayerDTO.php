<?php
namespace App\DTO;

use App\Entity\Game;
use App\Entity\Player;
use App\Entity\Team;
use Doctrine\Common\Collections\Collection;

class PlayerDTO
{
    public int $id;
    public string $pandascoreId;
    public string $name;
    public ?TeamDTO $currentTeam;
    public ?array $teams;
    public ?string $firstName;
    public ?string $lastName;
    public ?string $nationality;
    public string $slug;
    public ?string $image;
    public ?string $birthday;
    public ?array $crosshair;
    public array $socials;
    public ?string $bio;
    public ?array $skins;
    public ?string $totalWon;
    public ?array $stats;
    public ?int $age;
    public ?array $last_games;
    public ?array $lastGames;

    public function __construct(Player $player)
    {
        $this->id = $player->getId();
        $this->pandascoreId = $player->getPandascoreId();
        $this->name = $player->getName();
        $this->firstName = $player->getFirstName();
        $this->lastName = $player->getLastName();
        $this->nationality = $player->getNationality();
        $this->slug = $player->getSlug();
        $this->image = $player->getImage();
        $this->birthday = $player->getBirthday()?->format('Y-m-d');
        $this->age = null;
        $this->crosshair = $player->getCrosshair();
        $this->socials = $player->getSocials();
        $this->bio = $player->getBio();
        $this->totalWon = $player->getTotalWon();
        $this->stats = $player->getStats();

        $this->currentTeam = $player->getCurrentTeam() ? new TeamDTO($player->getCurrentTeam(), $player) : null;

        $this->teams = $this->mapTeams($player->getTeams());

        $this->skins = $this->mapSkins($player->getSkins());
        $this->last_games = $player->getLastGames();
        $this->lastGames = $this->getPlayerLastGames($player);
    }

    private function mapTeams(Collection $teams): array
    {
        return array_map(fn($team) => [
            'id' => $team->getId(),
            'name' => $team->getName(),
            'slug' => $team->getSlug(),
            'image' => $team->getImage(),
        ], $teams->toArray());
    }

    private function mapSkins(Collection $skins): array
    {
        return array_map(fn($skin) => new SkinDTO($skin), $skins->toArray());
    }

    function getPlayerLastGames(Player $player): ?array
    {
        $team = $player->getCurrentTeam();
        if (!$team){
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
}
