<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tournament;
use App\Repository\PlayerRepository;
use App\Repository\TeamRepository;
use App\Repository\TournamentRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

class TournamentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TournamentRepository   $tournamentRepository,
        private readonly TeamRepository         $teamRepository,
        private readonly PlayerRepository       $playerRepository
    )
    {
    }

    /**
     * Получаем данные из запроса в пандаскор, проверяем и добавляем их в базу пачкой
     *
     * @param array $tournaments
     * @return void
     * @throws Exception
     */
    public function syncBatchFromApi(array $tournaments): void
    {
        $ids = array_column($tournaments, 'id');
        $existing = $this->tournamentRepository->findBy(['tournamentId' => $ids]);

        $map = [];
        foreach ($existing as $tournament) {
            $map[$tournament->getTournamentId()] = $tournament;
        }
        unset($existing);

        foreach ($tournaments as $data) {
            $id = $data['id'];
            $tournament = $map[$id] ?? new Tournament();

            $tournament->setTournamentId($data['id']);
            $tournament->setName($data['name']);
            $tournament->setSlug($data['slug'] ?? null);
            $tournament->setBeginAt(!empty($data['begin_at']) ? new DateTimeImmutable($data['begin_at']) : null);
            $tournament->setEndAt(!empty($data['end_at']) ? new DateTimeImmutable($data['end_at']) : null);
            $tournament->setCountry($data['country'] ?? null);
            $tournament->setDetailedStats($data['detailed_stats']);
            $tournament->setHasBracket($data['has_bracket']);
            $tournament->setLeagueId($data['league_id']);
            $tournament->setLeague($data['league'] ?? null);
            $tournament->setLiveSupported($data['live_supported']);
            $tournament->setMatches($data['matches'] ?? null);
            $tournament->setExpectedRoster($data['expected_roster'] ?? null);
            $tournament->setParsedTeams($data['teams'] ?? null);
            $tournament->setPrizepool($data['prizepool'] ?? null);
            $tournament->setRegion($data['region'] ?? null);
            $tournament->setSerieId($data['serie_id']);
            $tournament->setSerie($data['serie'] ?? null);
            $tournament->setTier($data['tier'] ?? null);
            $tournament->setType($data['type'] ?? null);
            $tournament->setWinnerId($data['winner_id'] ?? null);
            $tournament->setWinnerType($data['winner_type'] ?? null);

            $this->entityManager->persist($tournament);
            unset($data, $tournament);
        }

        $this->entityManager->flush();
        unset($map, $tournaments, $ids);
        gc_collect_cycles();
    }

    /**
     * Линковка турнира к игрокам и командам
     *
     * @param Tournament $tournament
     * @return void
     */
    public function linkTeamsAndPlayers(Tournament $tournament): void
    {
        $expectedRoster = $tournament->getExpectedRoster();

        if (!is_array($expectedRoster)) {
            return;
        }

        foreach ($expectedRoster as $entry) {
            $teamData = $entry['team'] ?? null;
            $playersData = $entry['players'] ?? [];

            if (!isset($teamData['id'])) {
                continue;
            }

            $team = $this->teamRepository->findOneBy(['pandascoreId' => (string)$teamData['id']]);
            if ($team) {
                $tournament->addTeam($team);
            }

            foreach ($playersData as $playerData) {
                if (!isset($playerData['id'])) {
                    continue;
                }

                $player = $this->playerRepository->findOneBy(['pandascoreId' => (string)$playerData['id']]);
                if ($player) {
                    $tournament->addPlayer($player);
                }
            }
        }
    }
}
