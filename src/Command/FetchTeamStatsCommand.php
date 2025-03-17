<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\Player;
use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:fetch-team-stats',
    description: 'Fetch team stats from PandaScore API'
)]
class FetchTeamStatsCommand extends Command
{
    private const API_URL = 'https://api.pandascore.co/csgo/teams/%s/stats';
    private const RATE_LIMIT = 2; // 2 запроса в секунду
    private const BATCH_SIZE = 50; // Уменьшенный размер пакета для улучшенного контроля памяти
    private const MAX_CONCURRENT_REQUESTS = 10; // Уменьшено для снижения нагрузки на память
    private const MEMORY_THRESHOLD = 150 * 1024 * 1024; // 150MB пороговое значение памяти

    public function __construct(
        private readonly HttpClientInterface    $httpClient,
        private readonly EntityManagerInterface $entityManager
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Fetching team stats from PandaScore API');

        // Используем прямой SQL для получения количества
        $conn = $this->entityManager->getConnection();
        $totalTeams = (int)$conn->fetchOne('SELECT COUNT(id) FROM team');

        if (!$totalTeams) {
            $io->warning('No teams found.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Total teams to process: %d', $totalTeams));

        // Обрабатываем команды пакетами
        $offset = 0;
        $totalProcessed = 0;

        while (true) {
            // Очищаем кэш перед загрузкой нового пакета
            $this->entityManager->clear();

            // Принудительный сбор мусора
            gc_disable();
            gc_collect_cycles();
            gc_enable();

            // Мониторинг памяти
            $memoryBefore = memory_get_usage(true);
            $io->info(sprintf('Memory usage before batch: %d MB', round($memoryBefore / 1024 / 1024)));

            // Проверяем, не превышен ли порог памяти
            if ($memoryBefore > self::MEMORY_THRESHOLD) {
                $io->warning('Memory threshold reached, pausing for memory cleanup');
                sleep(2); // Даем системе время освободить память
                continue; // Повторяем проверку памяти без загрузки новых данных
            }

            // Загружаем пакет команд через прямой запрос
            $teamRows = $conn->fetchAllAssociative(
                'SELECT id, name, pandascore_id, slug FROM team ORDER BY stats DESC LIMIT ? OFFSET ?',
                [self::BATCH_SIZE, $offset]
            );

            if (empty($teamRows)) {
                break;
            }

            // Преобразуем строки в объекты Team (легкие прокси)
            $teams = [];
            foreach ($teamRows as $row) {
                $team = $this->entityManager->getReference(Team::class, $row['id']);
                $teams[] = [
                    'entity' => $team,
                    'name' => $row['name'],
                    'pandascore_id' => $row['pandascore_id'],
                    'slug' => $row['slug']
                ];
            }

            $io->info(sprintf('Processing batch of %d teams (offset: %d)...', count($teams), $offset));

            // Загружаем карту игроков оптимизированным способом
            $playersMap = $this->loadPlayersMap();

            // Обрабатываем текущий пакет команд асинхронно
            $processed = $this->processTeamsBatch($teams, $io, $playersMap);

            try {
                // Сохраняем изменения
                $this->entityManager->flush();

                // Явно освобождаем переменные
                $teams = null;
                $teamRows = null;
                $playersMap = null;

                // Принудительно очищаем кэш Doctrine
                $this->entityManager->clear();

                // Принудительный сбор мусора
                gc_disable();
                gc_collect_cycles();
                gc_enable();
            } catch (\Exception $e) {
                $io->error('Error during flush: ' . $e->getMessage());
                return Command::FAILURE;
            }

            $offset += self::BATCH_SIZE;
            $totalProcessed += $processed;

            $memoryAfter = memory_get_usage(true);
            $io->info(sprintf('Memory usage after batch: %d MB, diff: %d MB',
                round($memoryAfter / 1024 / 1024),
                round(($memoryAfter - $memoryBefore) / 1024 / 1024)
            ));

            $io->info(sprintf('Progress: %d/%d teams processed (%.2f%%)',
                $totalProcessed,
                $totalTeams,
                ($totalProcessed / $totalTeams) * 100
            ));

            // Дополнительная пауза между пакетами для освобождения ресурсов
            usleep(200000); // 200 мс
        }

        $io->success(sprintf('All team stats fetched. Total processed: %d', $totalProcessed));

        return Command::SUCCESS;
    }

    private function processTeamsBatch(array $teams, SymfonyStyle $io, array $playersMap): int
    {
        $pendingRequests = [];
        $activeRequests = [];
        $teamMap = [];
        $processed = 0;
        $lastRequestTime = 0;
        $requestId = 0;

        // Подготовка всех запросов
        foreach ($teams as $teamData) {
            $pendingRequests[] = [
                'team' => $teamData['entity'],
                'name' => $teamData['name'],
                'url' => sprintf(self::API_URL, $teamData['pandascore_id'])
            ];
        }

        // Обработка запросов асинхронно с соблюдением лимита скорости
        while (!empty($pendingRequests) || !empty($activeRequests)) {
            // Мониторинг памяти внутри цикла
            if ($processed > 0 && $processed % 5 === 0) {
                $io->info(sprintf('Memory usage during processing: %d MB',
                    round(memory_get_usage(true) / 1024 / 1024)));
            }

            // Запускаем новые запросы с учетом ограничения скорости
            $now = microtime(true);
            while (!empty($pendingRequests) && count($activeRequests) < self::MAX_CONCURRENT_REQUESTS) {
                // Соблюдаем ограничение RateLimit
                if ($now - $lastRequestTime < (1 / self::RATE_LIMIT)) {
                    $sleepTime = (1 / self::RATE_LIMIT) - ($now - $lastRequestTime);
                    usleep((int)($sleepTime * 1_000_000));
                    $now = microtime(true);
                }

                $request = array_shift($pendingRequests);
                $team = $request['team'];
                $teamName = $request['name'];

                try {
                    // Создаем уникальный ID для запроса
                    $currentRequestId = 'request_' . $requestId++;

                    $response = $this->httpClient->request('GET', $request['url'], [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $_ENV['PANDASCORE_TOKEN'],
                            'Accept' => 'application/json',
                        ],
                        'timeout' => 30,
                        'user_data' => $currentRequestId,
                    ]);

                    $activeRequests[$currentRequestId] = $response;
                    $teamMap[$currentRequestId] = $team;

                    $io->text(sprintf('Request sent for team %s', $teamName));
                    $lastRequestTime = microtime(true);
                } catch (\Exception $e) {
                    $io->error(sprintf(
                        'Error requesting stats for team %s: %s',
                        $teamName,
                        $e->getMessage()
                    ));
                }
            }

            // Проверяем завершенные запросы
            foreach ($this->httpClient->stream($activeRequests) as $response => $chunk) {
                if ($chunk->isLast()) {
                    try {
                        // Получаем ID из user_data
                        $id = $response->getInfo('user_data');
                        $team = $teamMap[$id];

                        // Важно - удаляем ссылку на объект из массивов сразу
                        unset($activeRequests[$id]);
                        unset($teamMap[$id]);

                        $data = json_decode($response->getContent(), true);

                        if (!is_array($data)) {
                            throw new \Exception('Invalid JSON response');
                        }

                        $io->success('Stats fetched for team');
                        $this->updateTeamData($team, $data, $io, $playersMap);
                        $processed++;

                        // Очищаем данные ответа
                        $data = null;
                        $response = null;
                    } catch (\Exception $e) {
                        $io->error(sprintf(
                            'Error processing stats: %s',
                            $e->getMessage()
                        ));
                    }
                }
            }

            // Периодически освобождаем память
            if (count($activeRequests) === 0 && !empty($pendingRequests)) {
                gc_collect_cycles();
                usleep(100000); // 100 мс
            }
        }

        // Отсоединяем обработанные объекты
        foreach ($teams as $teamData) {
            $this->entityManager->detach($teamData['entity']);
        }

        // Очищаем ссылки на все объекты
        $pendingRequests = null;
        $activeRequests = null;
        $teamMap = null;

        return $processed;
    }

    private function updateTeamData(Team $team, array $data, SymfonyStyle $io, array $playersMap): void
    {
        try {
            // Используем прямой SQL для обновления
            $conn = $this->entityManager->getConnection();

            // Обрабатываем last_games с дополнительными запросами для каждой игры
            $enrichedGames = [];
            $lastRequestTime = microtime(true);
            $processedGameIds = [];

            if (!empty($data['last_games'])) {
                // Ограничиваем количество игр для обработки
                $gamesToProcess = array_slice($data['last_games'], 0, 5);

                foreach ($gamesToProcess as $game) {
                    if (empty($game['id'])) {
                        $enrichedGames[] = $game;
                        continue;
                    }

                    $gameId = $game['id'];
                    $processedGameIds[] = $gameId;

                    // Проверяем, существует ли игра уже в базе
                    $existingGame = $conn->fetchAssociative(
                        'SELECT id, data FROM game WHERE pandascore_id = ?',
                        [$gameId]
                    );

                    if ($existingGame) {
                        // Если игра существует, используем ее данные
                        $gameData = json_decode($existingGame['data'], true);
                        $enrichedGames[] = $gameData;

                        // Убеждаемся, что связь с командой существует
                        $this->ensureTeamGameRelation($team->getId(), $existingGame['id'], $conn);

                        continue;
                    }

                    // Соблюдаем ограничение RateLimit - 2 запроса в секунду
                    $now = microtime(true);
                    if ($now - $lastRequestTime < 0.5) { // 0.5 секунды = 2 запроса в секунду
                        $sleepTime = 0.5 - ($now - $lastRequestTime);
                        usleep((int)($sleepTime * 1_000_000));
                    }

                    try {
                        $response = $this->httpClient->request('GET', "https://api.pandascore.co/csgo/games/{$gameId}", [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $_ENV['PANDASCORE_TOKEN'],
                                'Accept' => 'application/json',
                            ],
                            'timeout' => 10,
                        ]);

                        $gameData = json_decode($response->getContent(), true);
                        if (is_array($gameData)) {
                            // Объединяем базовую информацию с детальной
                            $enrichedGame = array_merge($game, $gameData);
                            $enrichedGames[] = $enrichedGame;

                            // Сохраняем игру в базу данных
                            $this->saveGameToDatabase($enrichedGame, $team->getId(), $conn);
                            $io->text(sprintf('Fetched and saved game %s to database', $gameId));
                        } else {
                            // Если что-то пошло не так, сохраняем оригинальные данные
                            $enrichedGames[] = $game;
                        }

                        $lastRequestTime = microtime(true);
                    } catch (\Exception $e) {
                        $io->warning(sprintf('Could not fetch game details for game %s: %s', $gameId, $e->getMessage()));
                        // Сохраняем оригинальные данные при ошибке
                        $enrichedGames[] = $game;
                    }

                    // Очищаем данные после каждой игры
                    $gameData = null;
                }
            }

            // Обновляем данные команды с обогащенными данными игр
            $lastGames = !empty($enrichedGames) ? json_encode($enrichedGames) : null;
            $stats = !empty($data['stats']) ? json_encode($data['stats']) : null;

            $conn->executeStatement(
                'UPDATE team SET last_games = :last_games, stats = :stats WHERE id = :id',
                [
                    'last_games' => $lastGames,
                    'stats' => $stats,
                    'id' => $team->getId()
                ]
            );

            // Обрабатываем связи с игроками
            if (!empty($data['players'])) {
                foreach ($data['players'] as $playerData) {
                    if (empty($playerData['id'])) {
                        continue;
                    }

                    $playerId = (string)$playerData['id'];
                    $player = $playersMap[$playerId] ?? null;

                    if ($player) {
                        // Проверяем, существует ли уже связь
                        $checkSql = 'SELECT COUNT(*) FROM player_teams WHERE team_id = ? AND player_id = ?';
                        $exists = (int)$conn->fetchOne($checkSql, [$team->getId(), $player]);

                        if ($exists === 0) {
                            // Добавляем связь
                            $conn->executeStatement(
                                'INSERT INTO player_teams (team_id, player_id) VALUES (?, ?)',
                                [$team->getId(), $player]
                            );
                        }
                    }
                }
            }

            // Очищаем ссылки на данные
            $data = null;
            $lastGames = null;
            $stats = null;
            $enrichedGames = null;
            $processedGameIds = null;
        } catch (\Exception $e) {
            $io->error('Error updating team data: ' . $e->getMessage());
        }
    }

    private function saveGameToDatabase(array $gameData, int $teamId, $conn): void
    {
        try {
            $gameId = $gameData['id'];
            $gameName = $gameData['match']['name'];
            $beginAt = $gameData['begin_at'] ?? null;
            $endAt = $gameData['end_at'] ?? null;
            $status = $gameData['status'] ?? 'unknown';
            $matchData = $gameData['match'] ? json_encode($gameData['match']) : null;
            $mapData = $gameData['map'] ? json_encode($gameData['map']) : null;
            $winnerData = $gameData['winner'] ? json_encode($gameData['winner']) : null;
            $roundsData = $gameData['rounds'] ? json_encode($gameData['rounds']) : null;
            $roundsScoreData = $gameData['rounds_score'] ? json_encode($gameData['rounds_score']) : null;
            $results = $gameData['match']['results'] ? json_encode($gameData['match']['results']) : null;

            // Определяем турнир, если он есть в данных
            $tournamentId = null;
            if (!empty($gameData['match']) && !empty($gameData['match']['tournament']) && !empty($gameData['match']['tournament']['id'])) {
                $tournamentPandaId = $gameData['match']['tournament']['id'];
                $tournamentResult = $conn->fetchOne(
                    'SELECT id FROM tournaments WHERE tournament_id = ?',
                    [$tournamentPandaId]
                );

                if ($tournamentResult && is_numeric($tournamentResult)) {
                    $tournamentId = (int)$tournamentResult;
                }
            }

            // Проверяем, существует ли уже эта игра
            $existingId = $conn->fetchOne(
                'SELECT id FROM game WHERE pandascore_id = ?',
                [$gameId]
            );

            if ($existingId) {
                // Игра уже существует, обновляем данные
                $updateSql = 'UPDATE game SET 
                     name = ?, begin_at = ?, end_at = ?, status = ?, 
                     "match" = ?, map = ?, winner = ?, rounds = ?, rounds_score = ?,
                     tournament_id = ? , results = ?, data = ?
                     WHERE id = ?';

                $conn->executeStatement(
                    $updateSql,
                    [
                        $gameName,
                        $beginAt,
                        $endAt,
                        $status,
                        $matchData,
                        $mapData,
                        $winnerData,
                        $roundsData,
                        $roundsScoreData,
                        $tournamentId,
                        $results,
                        json_encode($gameData),
                        $existingId
                    ]
                );

                // Убеждаемся, что связь с командой существует
                $this->ensureTeamGameRelation($teamId, $existingId, $conn);
                if ($roundsScoreData) {
                    $rd = json_decode($roundsScoreData, true);
                    error_log('in existing checking for team ' . $teamId);
                    error_log(json_encode($rd));
                    $opponentTeamId = null;
                    foreach ($rd as $teamScore) {
                        if ($teamScore['team_id'] != $teamId) {
                            $opponentTeamId = $teamScore['team_id'];
                            break;
                        }
                    }
                    if ($opponentTeamId) {
                        $opponentId = $conn->fetchOne(
                            'SELECT id FROM team WHERE pandascore_id = ?',
                            [$opponentTeamId]
                        );

                        if ($opponentId) {
                            error_log('inserting ' . $opponentId . ' as opponent id for existing game id:' . $existingId);
                            $this->ensureTeamGameRelation((int)$opponentId, (int)$existingId, $conn);
                        }
                    }
                }

                return;
            }

            // Создаем новую запись в таблице game
            $insertSql = 'INSERT INTO game (pandascore_id, name, "match", map, begin_at, end_at, winner, rounds, rounds_score, status, tournament_id, results, data, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())';

            $conn->executeStatement(
                $insertSql,
                [
                    $gameId,
                    $gameName,
                    $matchData,
                    $mapData,
                    $beginAt,
                    $endAt,
                    $winnerData,
                    $roundsData,
                    $roundsScoreData,
                    $status,
                    $tournamentId,
                    $results,
                    json_encode($gameData)
                ]
            );

            // Получаем ID новой записи
            $newGameId = $conn->lastInsertId();

            // Создаем связь между командой и игрой
            $this->ensureTeamGameRelation($teamId, (int)$newGameId, $conn);
            error_log('inserting ' . $teamId . ' as team id for new game id: ' . $newGameId);
            if ($roundsScoreData) {
                $rd = json_decode($roundsScoreData, true);
                $opponentTeamId = null;
                foreach ($rd as $teamScore) {
                    if ($teamScore['team_id'] != $teamId) {
                        $opponentTeamId = $teamScore['team_id'];
                        break;
                    }
                }
                if ($opponentTeamId) {
                    $opponentId = $conn->fetchOne(
                        'SELECT id FROM team WHERE pandascore_id = ?',
                        [$opponentTeamId]
                    );

                    if ($opponentId) {
                        error_log('inserting ' . $opponentId . ' as opponent id for new game id: ' . $newGameId);
                        $this->ensureTeamGameRelation((int)$opponentId, (int)$newGameId, $conn);
                    }
                }
            }
        } catch (\Exception $e) {
            // При ошибке просто логируем, но не прерываем весь процесс
            error_log('Error saving game: ' . $e->getMessage());
        }
    }

    private function ensureTeamGameRelation(int $teamId, int $gameId, $conn): void
    {
        $startTime = microtime(true);
        $timeout = 5; // 5 секунд максимум

        try {
            // Проверяем, не длится ли операция слишком долго
            if (microtime(true) - $startTime > $timeout) {
                error_log("Timeout checking relation existence, skipping...");
                return;
            }

            // Проверка на существование связи без блокировки
            try {
                $conn->executeStatement('SET LOCAL statement_timeout = 5000'); // 5 секунд в миллисекундах

                // Используем INSERT ON CONFLICT DO NOTHING для PostgreSQL
                $conn->executeStatement(
                    'INSERT INTO team_game (team_id, game_id) VALUES (?, ?) ON CONFLICT (team_id, game_id) DO NOTHING',
                    [$teamId, $gameId]
                );
            } catch (\Exception $e) {
                // Если произошла ошибка, пробуем традиционный подход
                $exists = (int)$conn->fetchOne(
                    'SELECT COUNT(*) FROM team_game WHERE team_id = ? AND game_id = ?',
                    [$teamId, $gameId]
                );

                // Проверяем таймаут перед вставкой
                if (microtime(true) - $startTime > $timeout) {
                    error_log("Timeout after checking relation, skipping insertion...");
                    return;
                }

                if ($exists === 0) {
                    $conn->executeStatement(
                        'INSERT INTO team_game (team_id, game_id) VALUES (?, ?)',
                        [$teamId, $gameId]
                    );
                }
            }
        } catch (\Exception $e) {
            error_log("Error in ensureTeamGameRelation: " . $e->getMessage());
        }
    }

    private function loadPlayersMap(): array
    {
        // Используем прямой SQL для оптимизации
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT id, pandascore_id FROM player';
        $result = $conn->executeQuery($sql);

        $playersMap = [];
        while ($row = $result->fetchAssociative()) {
            // Храним только ID игрока, а не полный объект
            $playersMap[$row['pandascore_id']] = $row['id'];
        }

        return $playersMap;
    }
}