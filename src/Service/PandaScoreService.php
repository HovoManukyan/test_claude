<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Player;
use App\Entity\Team;
use App\Entity\Tournament;
use App\Entity\Game;
use App\Repository\PlayerRepository;
use App\Repository\TeamRepository;
use App\Repository\TournamentRepository;
use App\Repository\GameRepository;
use App\Service\Http\HttpClientService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service for interacting with the PandaScore API
 */
class PandaScoreService
{
    /**
     * @param HttpClientService $httpClient HTTP client for PandaScore API
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     * @param FileService $fileService File service for downloading images
     * @param PlayerRepository $playerRepository Player repository
     * @param TeamRepository $teamRepository Team repository
     * @param TournamentRepository $tournamentRepository Tournament repository
     * @param GameRepository $gameRepository Game repository
     * @param LoggerInterface $logger Logger
     * @param string $pandascoreBaseUrl PandaScore API base URL
     * @param string $pandascoreToken PandaScore API token
     */
    public function __construct(
        private readonly HttpClientService      $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly FileService            $fileService,
        private readonly PlayerRepository       $playerRepository,
        private readonly TeamRepository         $teamRepository,
        private readonly TournamentRepository   $tournamentRepository,
        private readonly GameRepository         $gameRepository,
        private readonly LoggerInterface        $logger,
        #[Autowire('%pandascore.api_base_url%')]
        private readonly string                 $pandascoreBaseUrl,
        #[Autowire('%pandascore.token%')]
        private readonly string                 $pandascoreToken,
    )
    {
    }

    /**
     * Fetch players from PandaScore API and save to database
     *
     * @param int $page Page number
     * @param int $limit Results per page
     * @return int Number of players saved
     */
    public function fetchPlayers(int $page, int $limit): int
    {
        try {
            // Fetch players from API
            $data = $this->httpClient->get('/csgo/players', [
                'page' => $page,
                'per_page' => $limit,
            ]);

            $savedCount = 0;

            foreach ($data as $playerData) {
                // Skip invalid data
                if (empty($playerData['id'])) {
                    continue;
                }

                $playerId = (string)$playerData['id'];

                // Check if player already exists
                $player = $this->playerRepository->findOneBy(['pandascoreId' => $playerId]);

                // Create new player if not exists
                if (!$player) {
                    $player = new Player();
                    $player->setPandascoreId($playerId);
                }

                // Update player data
                $player->setName($playerData['name'] ?? 'Unknown');
                $player->setFirstName($playerData['first_name'] ?? null);
                $player->setLastName($playerData['last_name'] ?? null);
                $player->setSlug($playerData['slug'] ?? $this->playerRepository->generateUniqueSlug($player->getName()));
                $player->setNationality($playerData['nationality'] ?? null);

                // Parse birthday if exists
                if (!empty($playerData['birthday'])) {
                    try {
                        $player->setBirthday(new DateTimeImmutable($playerData['birthday']));
                    } catch (\Exception $e) {
                        $this->logger->warning('Invalid birthday format', [
                            'player_id' => $playerId,
                            'birthday' => $playerData['birthday'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Download and set image if exists
                if (!empty($playerData['image_url'])) {
                    $imagePath = $this->fileService->downloadPlayerImage($playerData['image_url'], $playerId);
                    if ($imagePath) {
                        $player->setImage($imagePath);
                    }
                }

                // Link to current team if exists
                if (!empty($playerData['current_team']['id'])) {
                    $teamId = (string)$playerData['current_team']['id'];
                    $team = $this->teamRepository->findOneBy(['pandascoreId' => $teamId]);

                    // If team doesn't exist, create it
                    if (!$team && !empty($playerData['current_team'])) {
                        $team = $this->createTeamFromData($playerData['current_team']);
                    }

                    // Set current team and add to teams collection
                    if ($team) {
                        $player->setCurrentTeam($team);
                        $player->addTeam($team);
                    }
                }

                // Save player
                $this->entityManager->persist($player);
                $savedCount++;
            }

            // Flush changes
            $this->entityManager->flush();

            return $savedCount;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching players from PandaScore API', [
                'page' => $page,
                'limit' => $limit,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Fetch teams from PandaScore API and save to database
     *
     * @param int $page Page number
     * @param int $limit Results per page
     * @return int Number of teams saved
     */
    public function fetchTeams(int $page, int $limit): int
    {
        try {
            // Fetch teams from API
            $data = $this->httpClient->get('/csgo/teams', [
                'page' => $page,
                'per_page' => $limit,
            ]);

            $savedCount = 0;

            foreach ($data as $teamData) {
                // Skip invalid data
                if (empty($teamData['id'])) {
                    continue;
                }

                $teamId = (string)$teamData['id'];

                // Check if team already exists
                $team = $this->teamRepository->findOneBy(['pandascoreId' => $teamId]);

                // Create new team if not exists or update existing
                if (!$team) {
                    $team = $this->createTeamFromData($teamData);
                    $savedCount++;
                } else {
                    $this->updateTeamFromData($team, $teamData);
                }

                // Save team
                $this->entityManager->persist($team);
            }

            // Flush changes
            $this->entityManager->flush();

            return $savedCount;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching teams from PandaScore API', [
                'page' => $page,
                'limit' => $limit,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create a team from API data
     *
     * @param array $teamData Team data from API
     * @return Team Created team entity
     */
    private function createTeamFromData(array $teamData): Team
    {
        $team = new Team();
        $team->setPandascoreId((string)$teamData['id']);
        $team->setName($teamData['name'] ?? 'Unknown');
        $team->setSlug($teamData['slug'] ?? $this->teamRepository->generateUniqueSlug($team->getName()));
        $team->setAcronym($teamData['acronym'] ?? null);
        $team->setLocation($teamData['location'] ?? null);

        // Download and set image if exists
        if (!empty($teamData['image_url'])) {
            $imagePath = $this->fileService->downloadTeamLogo($teamData['image_url'], $team->getPandascoreId());
            if ($imagePath) {
                $team->setImage($imagePath);
            }
        }

        return $team;
    }

    /**
     * Update a team from API data
     *
     * @param Team $team Team entity to update
     * @param array $teamData Team data from API
     * @return Team Updated team entity
     */
    private function updateTeamFromData(Team $team, array $teamData): Team
    {
        // Only update certain fields
        $team->setName($teamData['name'] ?? $team->getName());
        $team->setAcronym($teamData['acronym'] ?? $team->getAcronym());
        $team->setLocation($teamData['location'] ?? $team->getLocation());

        // Update image if exists and team doesn't have one yet
        if (!$team->getImage() && !empty($teamData['image_url'])) {
            $imagePath = $this->fileService->downloadTeamLogo($teamData['image_url'], $team->getPandascoreId());
            if ($imagePath) {
                $team->setImage($imagePath);
            }
        }

        return $team;
    }

    /**
     * Fetch player statistics from PandaScore API
     *
     * @param Player $player Player entity
     * @return bool True if successful
     */
    public function fetchPlayerStats(Player $player): bool
    {
        try {
            // Fetch player stats from API
            $data = $this->httpClient->get("/csgo/players/{$player->getPandascoreId()}/stats");

            // Update player with stats data
            if (isset($data['stats'])) {
                $player->setStats($data['stats']);
            }

            if (isset($data['last_games'])) {
                $player->setLastGames($data['last_games']);

                // Process last games data
                $this->processPlayerLastGames($player, $data['last_games']);
            }

            // Update teams if available
            if (!empty($data['teams'])) {
                foreach ($data['teams'] as $teamData) {
                    if (empty($teamData['id'])) {
                        continue;
                    }

                    $teamId = (string)$teamData['id'];
                    $team = $this->teamRepository->findOneBy(['pandascoreId' => $teamId]);

                    // If team doesn't exist, create it
                    if (!$team) {
                        $team = $this->createTeamFromData($teamData);
                        $this->entityManager->persist($team);
                    }

                    // Add team to player's teams
                    $player->addTeam($team);
                }
            }

            // Save changes
            $this->entityManager->persist($player);
            $this->entityManager->flush();

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching player stats from PandaScore API', [
                'player_id' => $player->getPandascoreId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Process player's last games data
     *
     * @param Player $player Player entity
     * @param array $lastGames Last games data from API
     */
    private function processPlayerLastGames(Player $player, array $lastGames): void
    {
        foreach ($lastGames as $gameData) {
            if (empty($gameData['id'])) {
                continue;
            }

            $gameId = (string)$gameData['id'];
            $game = $this->gameRepository->findOneBy(['pandascoreId' => $gameId]);

            // If game doesn't exist, fetch it from API
            if (!$game) {
                try {
                    $fullGameData = $this->httpClient->get("/csgo/games/{$gameId}");
                    $game = $this->createOrUpdateGameFromData($fullGameData);
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to fetch game details', [
                        'game_id' => $gameId,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            // Add player to game
            if ($game) {
                $game->addPlayer($player);
                $this->entityManager->persist($game);
            }
        }
    }

    /**
     * Fetch team statistics from PandaScore API
     *
     * @param Team $team Team entity
     * @return bool True if successful
     */
    public function fetchTeamStats(Team $team): bool
    {
        try {
            // Fetch team stats from API
            $data = $this->httpClient->get("/csgo/teams/{$team->getPandascoreId()}/stats");

            // Update team with stats data
            if (isset($data['stats'])) {
                $team->setStats($data['stats']);
            }

            if (isset($data['last_games'])) {
                $team->setLastGames($data['last_games']);

                // Process last games data
                $this->processTeamLastGames($team, $data['last_games']);
            }

            // Update players if available
            if (!empty($data['players'])) {
                foreach ($data['players'] as $playerData) {
                    if (empty($playerData['id'])) {
                        continue;
                    }

                    $playerId = (string)$playerData['id'];
                    $player = $this->playerRepository->findOneBy(['pandascoreId' => $playerId]);

                    // If player doesn't exist, we don't create them here
                    if ($player) {
                        // Add team to player's teams
                        $player->addTeam($team);
                        $this->entityManager->persist($player);
                    }
                }
            }

            // Save changes
            $this->entityManager->persist($team);
            $this->entityManager->flush();

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching team stats from PandaScore API', [
                'team_id' => $team->getPandascoreId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Create or update a game from API data
     *
     * @param array $gameData Game data from API
     * @return Game Game entity
     */
    private function createOrUpdateGameFromData(array $gameData): Game
    {
        $gameId = (string)$gameData['id'];

        // Check if game already exists
        $game = $this->gameRepository->findOneBy(['pandascoreId' => $gameId]);

        if (!$game) {
            $game = new Game();
            $game->setPandascoreId($gameId);
        }

        // Update game data
        $game->setName($gameData['name'] ?? 'Unknown');
        $game->setStatus($gameData['status'] ?? 'unknown');
        $game->setData($gameData);

        // Set match data
        if (!empty($gameData['match'])) {
            $game->setMatch($gameData['match']);

            // Link to tournament if exists
            if (!empty($gameData['match']['tournament']['id'])) {
                $tournamentId = (string)$gameData['match']['tournament']['id'];
                $tournament = $this->tournamentRepository->findOneBy(['tournamentId' => (int)$tournamentId]);

                if ($tournament) {
                    $game->setTournament($tournament);
                }
            }
        }

        // Set map data
        if (!empty($gameData['map'])) {
            $game->setMap($gameData['map']);
        }

        // Set timestamps
        if (!empty($gameData['begin_at'])) {
            try {
                $game->setBeginAt(new DateTimeImmutable($gameData['begin_at']));
            } catch (\Exception $e) {
                $this->logger->warning('Invalid begin_at format', [
                    'game_id' => $gameId,
                    'begin_at' => $gameData['begin_at'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!empty($gameData['end_at'])) {
            try {
                $game->setEndAt(new DateTimeImmutable($gameData['end_at']));
            } catch (\Exception $e) {
                $this->logger->warning('Invalid end_at format', [
                    'game_id' => $gameId,
                    'end_at' => $gameData['end_at'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Set winner data
        if (!empty($gameData['winner'])) {
            $game->setWinner($gameData['winner']);
        }

        // Set rounds data
        if (!empty($gameData['rounds'])) {
            $game->setRounds($gameData['rounds']);
        }

        // Set rounds score data
        if (!empty($gameData['rounds_score'])) {
            $game->setRoundsScore($gameData['rounds_score']);
        }

        // Set results
        if (!empty($gameData['results'])) {
            $game->setResults($gameData['results']);
        }

        // Set teams
        if (!empty($gameData['teams'])) {
            foreach ($gameData['teams'] as $teamData) {
                if (empty($teamData['id'])) {
                    continue;
                }

                $teamId = (string)$teamData['id'];
                $team = $this->teamRepository->findOneBy(['pandascoreId' => $teamId]);

                // If team doesn't exist, create it
                if (!$team) {
                    $team = $this->createTeamFromData($teamData);
                    $this->entityManager->persist($team);
                }

                // Add team to game
                if ($team) {
                    $game->addTeam($team);
                }
            }
        }

        // Save game
        $this->entityManager->persist($game);

        return $game;
    }

    /**
     * Fetch tournaments from PandaScore API
     *
     * @param int $page Page number
     * @param int $limit Results per page
     * @return int Number of tournaments saved
     */
    public function fetchTournaments(int $page, int $limit): int
    {
        try {
            // Fetch tournaments from API
            $data = $this->httpClient->get('/csgo/tournaments/past', [
                'page' => $page,
                'per_page' => $limit,
            ]);

            $savedCount = 0;

            foreach ($data as $tournamentData) {
                // Skip invalid data
                if (empty($tournamentData['id'])) {
                    continue;
                }

                $tournamentId = (int)$tournamentData['id'];

                // Check if tournament already exists
                $tournament = $this->tournamentRepository->findOneBy(['tournamentId' => $tournamentId]);

                // Create new tournament if not exists
                if (!$tournament) {
                    $tournament = new Tournament();
                    $tournament->setTournamentId($tournamentId);
                    $savedCount++;
                }

                // Update tournament data
                $tournament->setName($tournamentData['name'] ?? 'Unknown');
                $tournament->setSlug($tournamentData['slug'] ?? null);

                // Parse begin/end dates
                if (!empty($tournamentData['begin_at'])) {
                    try {
                        $tournament->setBeginAt(new DateTimeImmutable($tournamentData['begin_at']));
                    } catch (\Exception $e) {
                        $this->logger->warning('Invalid begin_at format', [
                            'tournament_id' => $tournamentId,
                            'begin_at' => $tournamentData['begin_at'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                if (!empty($tournamentData['end_at'])) {
                    try {
                        $tournament->setEndAt(new DateTimeImmutable($tournamentData['end_at']));
                    } catch (\Exception $e) {
                        $this->logger->warning('Invalid end_at format', [
                            'tournament_id' => $tournamentId,
                            'end_at' => $tournamentData['end_at'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Set other data
                $tournament->setCountry($tournamentData['country'] ?? null);
                $tournament->setDetailedStats($tournamentData['detailed_stats'] ?? false);
                $tournament->setHasBracket($tournamentData['has_bracket'] ?? false);
                $tournament->setLeagueId($tournamentData['league_id'] ?? 0);
                $tournament->setLeague($tournamentData['league'] ?? null);
                $tournament->setLiveSupported($tournamentData['live_supported'] ?? false);
                $tournament->setMatches($tournamentData['matches'] ?? null);
                $tournament->setExpectedRoster($tournamentData['expected_roster'] ?? null);
                $tournament->setParsedTeams($tournamentData['teams'] ?? null);
                $tournament->setPrizepool($tournamentData['prizepool'] ?? null);
                $tournament->setRegion($tournamentData['region'] ?? null);
                $tournament->setSerieId($tournamentData['serie_id'] ?? 0);
                $tournament->setSerie($tournamentData['serie'] ?? null);
                $tournament->setTier($tournamentData['tier'] ?? null);
                $tournament->setType($tournamentData['type'] ?? null);
                $tournament->setWinnerId($tournamentData['winner_id'] ?? null);
                $tournament->setWinnerType($tournamentData['winner_type'] ?? null);

                // Save tournament
                $this->entityManager->persist($tournament);
            }

            // Flush changes
            $this->entityManager->flush();

            return $savedCount;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching tournaments from PandaScore API', [
                'page' => $page,
                'limit' => $limit,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Link players and teams to tournaments
     *
     * @param Tournament $tournament Tournament entity
     * @return bool True if successful
     */
    public function linkTournamentEntities(Tournament $tournament): bool
    {
        try {
            // Process expected roster data to link players and teams
            $expectedRoster = $tournament->getExpectedRoster();

            if (!is_array($expectedRoster)) {
                return false;
            }

            foreach ($expectedRoster as $rosterData) {
                // Process team
                if (!empty($rosterData['team']['id'])) {
                    $teamId = (string)$rosterData['team']['id'];
                    $team = $this->teamRepository->findOneBy(['pandascoreId' => $teamId]);

                    if ($team) {
                        $tournament->addTeam($team);
                    }
                }

                // Process players
                if (!empty($rosterData['players'])) {
                    foreach ($rosterData['players'] as $playerData) {
                        if (empty($playerData['id'])) {
                            continue;
                        }

                        $playerId = (string)$playerData['id'];
                        $player = $this->playerRepository->findOneBy(['pandascoreId' => $playerId]);

                        if ($player) {
                            $tournament->addPlayer($player);
                        }
                    }
                }
            }

            // Save changes
            $this->entityManager->persist($tournament);
            $this->entityManager->flush();

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error linking tournament entities', [
                'tournament_id' => $tournament->getTournamentId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Process team data from the API (used for parallel processing)
     * Returns true if the team was created or updated, false otherwise
     *
     * @param array $teamData Team data from API
     * @return bool Success status
     */
    public function processTeamData(array $teamData): bool
    {
        try {
            // Skip invalid data
            if (empty($teamData['id'])) {
                $this->logger->warning('Skipping team with missing ID', [
                    'team_data' => json_encode(array_keys($teamData))
                ]);
                return false;
            }

            $teamId = (string)$teamData['id'];

            // Check if team already exists
            $team = $this->teamRepository->findOneBy(['pandascoreId' => $teamId]);

            // Create new team if not exists or update existing
            if (!$team) {
                $team = $this->createTeamFromData($teamData);
                $this->logger->info('Created new team', [
                    'team_id' => $teamId,
                    'team_name' => $teamData['name'] ?? 'Unknown'
                ]);
            } else {
                $team = $this->updateTeamFromData($team, $teamData);
                $this->logger->debug('Updated existing team', [
                    'team_id' => $teamId,
                    'team_name' => $team->getName()
                ]);
            }

            // Persist the team (but don't flush yet)
            $this->entityManager->persist($team);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error processing team data', [
                'team_id' => $teamData['id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Process player data from the API (used for parallel processing)
     * Returns true if the player was created or updated, false otherwise
     *
     * @param array $playerData Player data from API
     * @return bool Success status
     */
    public function processPlayerData(array $playerData): bool
    {
        try {
            // Skip invalid data
            if (empty($playerData['id'])) {
                $this->logger->warning('Skipping player with missing ID', [
                    'player_data' => json_encode(array_keys($playerData))
                ]);
                return false;
            }

            $playerId = (string)$playerData['id'];

            // Check if player already exists
            $player = $this->playerRepository->findOneBy(['pandascoreId' => $playerId]);

            // Create new player if not exists
            if (!$player) {
                $player = new Player();
                $player->setPandascoreId($playerId);
                $this->logger->info('Created new player', [
                    'player_id' => $playerId,
                    'player_name' => $playerData['name'] ?? 'Unknown'
                ]);
            } else {
                $this->logger->debug('Updating existing player', [
                    'player_id' => $playerId,
                    'player_name' => $player->getName()
                ]);
            }

            // Update player data
            $player->setName($playerData['name'] ?? 'Unknown');
            $player->setFirstName($playerData['first_name'] ?? null);
            $player->setLastName($playerData['last_name'] ?? null);
            $player->setSlug($playerData['slug'] ?? $this->playerRepository->generateUniqueSlug($player->getName()));
            $player->setNationality($playerData['nationality'] ?? null);

            // Parse birthday if exists
            if (!empty($playerData['birthday'])) {
                try {
                    $player->setBirthday(new DateTimeImmutable($playerData['birthday']));
                } catch (\Exception $e) {
                    $this->logger->warning('Invalid birthday format', [
                        'player_id' => $playerId,
                        'birthday' => $playerData['birthday'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Download and set image if exists
            if (!empty($playerData['image_url'])) {
                $imagePath = $this->fileService->downloadPlayerImage($playerData['image_url'], $playerId);
                if ($imagePath) {
                    $player->setImage($imagePath);
                }
            }

            // Link to current team if exists
            if (!empty($playerData['current_team']['id'])) {
                $teamId = (string)$playerData['current_team']['id'];
                $team = $this->teamRepository->findOneBy(['pandascoreId' => $teamId]);

                // If team doesn't exist, create it
                if (!$team && !empty($playerData['current_team'])) {
                    $team = $this->processTeamData($playerData['current_team']);
                }

                // Set current team and add to teams collection
                if ($team) {
                    $player->setCurrentTeam($team);
                    $player->addTeam($team);
                }
            }

            // Persist player (but don't flush yet)
            $this->entityManager->persist($player);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error processing player data', [
                'player_id' => $playerData['id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Flush all pending changes to the database
     * Should be called after batch processing
     *
     * @return void
     */
    public function flushChanges(): void
    {
        try {
            $this->entityManager->flush();
            $this->logger->debug('Successfully flushed entity changes to database');
        } catch (\Exception $e) {
            $this->logger->error('Error flushing changes to database', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Clear the entity manager to recover from error
            $this->entityManager->clear();

            throw $e;
        }
    }

    /**
     * Process tournament data from the API (used for parallel processing)
     * Returns true if the tournament was created or updated, false otherwise
     *
     * @param array $tournamentData Tournament data from API
     * @return bool Success status
     */
    public function processTournamentData(array $tournamentData): bool
    {
        try {
            // Skip invalid data
            if (empty($tournamentData['id'])) {
                $this->logger->warning('Skipping tournament with missing ID', [
                    'tournament_data' => json_encode(array_keys($tournamentData))
                ]);
                return false;
            }

            $tournamentId = (int)$tournamentData['id'];

            // Check if tournament already exists
            $tournament = $this->tournamentRepository->findOneBy(['tournamentId' => $tournamentId]);

            // Create new tournament if not exists
            if (!$tournament) {
                $tournament = new Tournament();
                $tournament->setTournamentId($tournamentId);
                $this->logger->info('Created new tournament', [
                    'tournament_id' => $tournamentId,
                    'tournament_name' => $tournamentData['name'] ?? 'Unknown'
                ]);
            } else {
                $this->logger->debug('Updating existing tournament', [
                    'tournament_id' => $tournamentId,
                    'tournament_name' => $tournament->getName()
                ]);
            }

            // Update tournament data
            $tournament->setName($tournamentData['name'] ?? 'Unknown');
            $tournament->setSlug($tournamentData['slug'] ?? null);

            // Parse begin/end dates
            if (!empty($tournamentData['begin_at'])) {
                try {
                    $tournament->setBeginAt(new \DateTimeImmutable($tournamentData['begin_at']));
                } catch (\Exception $e) {
                    $this->logger->warning('Invalid begin_at format', [
                        'tournament_id' => $tournamentId,
                        'begin_at' => $tournamentData['begin_at'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (!empty($tournamentData['end_at'])) {
                try {
                    $tournament->setEndAt(new \DateTimeImmutable($tournamentData['end_at']));
                } catch (\Exception $e) {
                    $this->logger->warning('Invalid end_at format', [
                        'tournament_id' => $tournamentId,
                        'end_at' => $tournamentData['end_at'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Set other data
            $tournament->setCountry($tournamentData['country'] ?? null);
            $tournament->setDetailedStats($tournamentData['detailed_stats'] ?? false);
            $tournament->setHasBracket($tournamentData['has_bracket'] ?? false);
            $tournament->setLeagueId($tournamentData['league_id'] ?? 0);
            $tournament->setLeague($tournamentData['league'] ?? null);
            $tournament->setLiveSupported($tournamentData['live_supported'] ?? false);
            $tournament->setMatches($tournamentData['matches'] ?? null);
            $tournament->setExpectedRoster($tournamentData['expected_roster'] ?? null);
            $tournament->setParsedTeams($tournamentData['teams'] ?? null);

            // Process prizepool if available
            if (!empty($tournamentData['prizepool'])) {
                $this->processTournamentPrizepool($tournament, $tournamentData['prizepool']);
            } else {
                $tournament->setPrizepool(null);
            }

            $tournament->setRegion($tournamentData['region'] ?? null);
            $tournament->setSerieId($tournamentData['serie_id'] ?? 0);
            $tournament->setSerie($tournamentData['serie'] ?? null);
            $tournament->setTier($tournamentData['tier'] ?? null);
            $tournament->setType($tournamentData['type'] ?? null);
            $tournament->setWinnerId($tournamentData['winner_id'] ?? null);
            $tournament->setWinnerType($tournamentData['winner_type'] ?? null);

            // Persist tournament (but don't flush yet)
            $this->entityManager->persist($tournament);

            // Process related entities if they're available
            if (!empty($tournamentData['teams'])) {
                $this->processTournamentTeams($tournament, $tournamentData['teams']);
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error processing tournament data', [
                'tournament_id' => $tournamentData['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Process tournament prizepool
     *
     * @param Tournament $tournament Tournament entity
     * @param string $prizepool Prizepool string from API
     * @return void
     */
    private function processTournamentPrizepool(Tournament $tournament, string $prizepool): void
    {
        // Map of currency names to codes
        $currencyMap = [
            "Turkish Lira" => "TRY",
            "Bulgarian Lev" => "BGN",
            "Japanese Yen" => "JPY",
            "Brazilian Real" => "BRL",
            "Czech Koruna" => "CZK",
            "Norwegian Krone" => "NOK",
            "Polish Zloty" => "PLN",
            "Australian Dollar" => "AUD",
            "Argentine Peso" => "ARS",
            "Danish Krone" => "DKK",
            "United States Dollar" => "USD",
            "Swiss Franc" => "CHF",
            "Qatari Riyal" => "QAR",
            "British Pound" => "GBP",
            "Chinese Yuan" => "CNY",
            "South African Rand" => "ZAR",
            "Ukrainian Hryvnia" => "UAH",
            "Swedish Krona" => "SEK",
            "Euro" => "EUR",
            "Russian Ruble" => "RUB",
            "Kazakhstani Tenge" => "KZT",
            "Croatian Kuna" => "HRK"
        ];

        try {
            // Extract currency name and amount
            $currencyName = preg_replace('/^[0-9, ]+/', '', $prizepool);
            $amount = (int)preg_replace('/\D/', '', $prizepool);

            if ($amount > 0) {
                // Get currency code
                $currencyCode = $currencyMap[$currencyName] ?? null;

                if ($currencyCode) {
                    // Convert to USD if appropriate
                    // Note: in a real application, you would use an API or database for current rates
                    // This is simplified for the example
                    $tournament->setPrizepool((string)$amount);
                    $this->logger->info('Processed tournament prizepool', [
                        'original' => $prizepool,
                        'amount' => $amount,
                        'currency' => $currencyCode
                    ]);
                } else {
                    $tournament->setPrizepool((string)$amount);
                    $this->logger->warning('Unknown currency in prizepool', [
                        'prizepool' => $prizepool,
                        'currency_name' => $currencyName
                    ]);
                }
            } else {
                $tournament->setPrizepool(null);
            }
        } catch (\Exception $e) {
            $this->logger->warning('Error processing prizepool', [
                'prizepool' => $prizepool,
                'error' => $e->getMessage()
            ]);
            $tournament->setPrizepool(null);
        }
    }

    /**
     * Process teams related to a tournament
     *
     * @param Tournament $tournament Tournament entity
     * @param array $teamsData Teams data from API
     * @return void
     */
    private function processTournamentTeams(Tournament $tournament, array $teamsData): void
    {
        foreach ($teamsData as $teamData) {
            if (empty($teamData['id'])) {
                continue;
            }

            $teamId = (string)$teamData['id'];
            $team = $this->teamRepository->findOneBy(['pandascoreId' => $teamId]);

            if ($team) {
                $tournament->addTeam($team);
                $this->logger->debug('Added team to tournament', [
                    'team_id' => $teamId,
                    'team_name' => $team->getName(),
                    'tournament_id' => $tournament->getTournamentId()
                ]);
            } else if (!empty($teamData)) {
                // Create new team and add to tournament
                $team = $this->processTeamData($teamData);
                if ($team && $team instanceof Team) {
                    $tournament->addTeam($team);
                    $this->logger->info('Created and added new team to tournament', [
                        'team_id' => $teamId,
                        'team_name' => $teamData['name'] ?? 'Unknown',
                        'tournament_id' => $tournament->getTournamentId()
                    ]);
                }
            }
        }
    }

    /**
     * Process team statistics data from API
     *
     * @param Team $team Team entity
     * @param array $data Team stats data from API
     * @return bool Success status
     */
    public function processTeamStats(Team $team, array $data): bool
    {
        try {
            // Update team with stats data
            if (isset($data['stats'])) {
                $team->setStats($data['stats']);
                $this->logger->debug('Updated stats for team', [
                    'team_id' => $team->getId(),
                    'team_name' => $team->getName()
                ]);
            }

            // Process last games data
            if (isset($data['last_games']) && is_array($data['last_games'])) {
                $team->setLastGames($data['last_games']);
                $this->processTeamLastGames($team, $data['last_games']);
            }

            // Update players if available
            if (!empty($data['players']) && is_array($data['players'])) {
                foreach ($data['players'] as $playerData) {
                    $this->processTeamPlayerRelation($team, $playerData);
                }
            }

            // Save changes
            $this->entityManager->persist($team);
            $this->entityManager->flush();

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error processing team stats', [
                'team_id' => $team->getId(),
                'team_name' => $team->getName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Process team's last games data
     *
     * @param Team $team Team entity
     * @param array $lastGames Last games data from API
     */
    private function processTeamLastGames(Team $team, array $lastGames): void
    {
        // Limit number of games to process to avoid excessive API calls
        $gamesToProcess = array_slice($lastGames, 0, 5);
        $processedGameIds = [];

        foreach ($gamesToProcess as $gameData) {
            if (empty($gameData['id'])) {
                continue;
            }

            $gameId = (string)$gameData['id'];

            // Skip duplicates
            if (in_array($gameId, $processedGameIds)) {
                continue;
            }

            $processedGameIds[] = $gameId;

            // Check if game already exists
            $game = $this->gameRepository->findOneBy(['pandascoreId' => $gameId]);

            // If game doesn't exist, fetch details and create it
            if (!$game) {
                try {
                    $fullGameData = $this->httpClient->get("/csgo/games/{$gameId}");
                    $game = $this->createOrUpdateGameFromData($fullGameData);

                    $this->logger->info('Created new game', [
                        'game_id' => $gameId,
                        'game_name' => $game->getName()
                    ]);
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to fetch game details', [
                        'game_id' => $gameId,
                        'team_id' => $team->getId(),
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            // Add team to game
            if ($game) {
                $game->addTeam($team);
                $this->entityManager->persist($game);

                $this->logger->debug('Added team to game', [
                    'team_id' => $team->getId(),
                    'team_name' => $team->getName(),
                    'game_id' => $game->getId(),
                    'game_name' => $game->getName()
                ]);
            }

            // Process opponent team if available in rounds score
            $this->processGameOpponents($game, $team);
        }
    }

    /**
     * Process team-player relationship from player data
     *
     * @param Team $team Team entity
     * @param array $playerData Player data from API
     */
    private function processTeamPlayerRelation(Team $team, array $playerData): void
    {
        if (empty($playerData['id'])) {
            return;
        }

        $playerId = (string)$playerData['id'];
        $player = $this->playerRepository->findOneBy(['pandascoreId' => $playerId]);

        // Skip if player not found and we don't have enough data to create one
        if (!$player && (empty($playerData['name']) || empty($playerData['slug']))) {
            $this->logger->debug('Player not found and insufficient data to create', [
                'player_id' => $playerId,
                'team_id' => $team->getId()
            ]);
            return;
        }

        // Create player if not exists but we have data
        if (!$player && !empty($playerData['name']) && !empty($playerData['slug'])) {
            $player = new Player();
            $player->setPandascoreId($playerId);
            $player->setName($playerData['name']);
            $player->setSlug($playerData['slug']);

            if (!empty($playerData['image_url'])) {
                $imagePath = $this->fileService->downloadPlayerImage($playerData['image_url'], $playerId);
                if ($imagePath) {
                    $player->setImage($imagePath);
                }
            }

            $this->entityManager->persist($player);

            $this->logger->info('Created new player from team stats', [
                'player_id' => $playerId,
                'player_name' => $player->getName(),
                'team_id' => $team->getId()
            ]);
        }

        // Add relationship if player exists
        if ($player) {
            // Add team to player's teams if not already there
            if (!$player->getTeams()->contains($team)) {
                $player->addTeam($team);
                $this->entityManager->persist($player);

                $this->logger->debug('Added team to player', [
                    'player_id' => $player->getId(),
                    'player_name' => $player->getName(),
                    'team_id' => $team->getId(),
                    'team_name' => $team->getName()
                ]);
            }
        }
    }

    /**
     * Process game opponents from rounds score data
     *
     * @param Game $game Game entity
     * @param Team $team Current team entity
     */
    private function processGameOpponents(Game $game, Team $team): void
    {
        $roundsScore = $game->getRoundsScore();

        if (!$roundsScore || !is_array($roundsScore)) {
            return;
        }

        foreach ($roundsScore as $teamScore) {
            // Skip current team
            if ($teamScore['team_id'] == $team->getPandascoreId()) {
                continue;
            }

            // Find opponent team
            $opponentTeamId = $teamScore['team_id'];
            $opponentTeam = $this->teamRepository->findOneBy(['pandascoreId' => (string)$opponentTeamId]);

            // If opponent team exists, add to game
            if ($opponentTeam) {
                $game->addTeam($opponentTeam);
                $this->entityManager->persist($game);

                $this->logger->debug('Added opponent team to game', [
                    'team_id' => $team->getId(),
                    'opponent_id' => $opponentTeam->getId(),
                    'game_id' => $game->getId()
                ]);
            }
        }
    }

    /**
     * Process player statistics data from API
     *
     * @param Player $player Player entity
     * @param array $data Player stats data from API
     * @return bool Success status
     */
    public function processPlayerStats(Player $player, array $data): bool
    {
        try {
            // Update player with stats data
            if (isset($data['stats'])) {
                $player->setStats($data['stats']);
                $this->logger->debug('Updated stats for player', [
                    'player_id' => $player->getId(),
                    'player_name' => $player->getName()
                ]);
            }

            // Process last games data
            if (isset($data['last_games']) && is_array($data['last_games'])) {
                $player->setLastGames($data['last_games']);
                $this->processPlayerLastGames($player, $data['last_games']);
            }

            // Update teams if available
            if (!empty($data['teams']) && is_array($data['teams'])) {
                foreach ($data['teams'] as $teamData) {
                    if (empty($teamData['id'])) {
                        continue;
                    }

                    $teamId = (string)$teamData['id'];
                    $team = $this->teamRepository->findOneBy(['pandascoreId' => $teamId]);

                    // If team doesn't exist, create it
                    if (!$team && !empty($teamData)) {
                        // Create new team and save
                        $team = $this->processTeamData($teamData);
                    }

                    // Add team to player's teams if it exists and not already there
                    if ($team && $team instanceof Team && !$player->getTeams()->contains($team)) {
                        $player->addTeam($team);
                    }
                }
            }

            // Save changes
            $this->entityManager->persist($player);
            $this->entityManager->flush();

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error processing player stats data', [
                'player_id' => $player->getId(),
                'player_name' => $player->getName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }
}