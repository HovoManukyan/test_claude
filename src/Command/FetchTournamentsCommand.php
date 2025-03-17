<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Team;
use App\Entity\Tournament;
use DateTimeImmutable;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'app:fetch-tournaments',
    description: 'Fetch CS:GO tournaments from PandaScore API',
)]
class FetchTournamentsCommand extends Command
{
    private const API_URL = 'https://api.pandascore.co/csgo/tournaments/past';
    private const PER_PAGE = 100;
    private const CONCURRENT_REQUESTS = 2;
    private const REQUEST_DELAY = 0.5;

    private const CURRENCY_MAP = [
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


    public function __construct(
        private readonly HttpClientInterface    $httpClient,
        private readonly EntityManagerInterface $entityManager
    )
    {
        parent::__construct();
    }

    /**
     * @throws TransportExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '512M');

        $io = new SymfonyStyle($input, $output);
        $io->title('Fetching CS:GO tournaments from PandaScore API');

        $page = 1;
        $hasMoreData = true;
        $totalSavedTournaments = 0;
        $issetTournaments = [];
        $jabkaCurrency = file_get_contents('https://jabka.skin/cdn/currencies/rates.json');
        $jabkaCurrency = json_decode($jabkaCurrency, true);
        while ($hasMoreData) {
            $requests = [];

            for ($i = 0; $i < self::CONCURRENT_REQUESTS; $i++) {
                $pageNum = $page + $i;
                $url = sprintf('%s?page=%d&per_page=%d', self::API_URL, $pageNum, self::PER_PAGE);

                $requests[$pageNum] = [
                    'page' => $pageNum,
                    'request' => $this->httpClient->request('GET', $url, [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $_ENV['PANDASCORE_TOKEN'],
                            'Accept' => 'application/json'
                        ]
                    ])
                ];
            }

            $emptyResponses = 0;

            foreach ($requests as $requestData) {
                try {
                    $pageNum = $requestData['page'];
                    $response = $requestData['request'];
                    $data = json_decode($response->getContent(), true);

                    if (!is_array($data)) {
                        throw new Exception('Invalid JSON response');
                    }

                    if (empty($data)) {
                        $emptyResponses++;
                        $io->warning(sprintf('Page %d is empty, stopping soon...', $pageNum));
                        continue;
                    }

                    $io->success(sprintf('Page %d fetched, %d tournaments found', $pageNum, count($data)));

                    $tournaments = [];
                    foreach ($data as $tournamentData) {
                        if (in_array($tournamentData['id'], $issetTournaments)) {
                            $io->info('xuy evo znayet xi erkrord angama ancnum ' . $tournamentData['id'] . ' es ccoxi vrov');
                            continue;
                        }
                        $issetTournaments[] = $tournamentData['id'];
                        $tournament = new Tournament();
                        $tournament->setTournamentId($tournamentData['id']);
                        $tournament->setName($tournamentData['name']);
                        $tournament->setSlug($tournamentData['slug'] ?? null);
                        $tournament->setBeginAt($this->convertToDateTime($tournamentData['begin_at']));
                        $tournament->setEndAt($this->convertToDateTime($tournamentData['end_at']));
                        $tournament->setCountry($tournamentData['country'] ?? null);
                        $tournament->setDetailedStats($tournamentData['detailed_stats']);
                        $tournament->setHasBracket($tournamentData['has_bracket']);
                        $tournament->setLeagueId($tournamentData['league_id']);
                        $tournament->setLeague($tournamentData['league'] ?? null);
                        $tournament->setLiveSupported($tournamentData['live_supported']);
                        $tournament->setMatches($tournamentData['matches'] ?? null);
                        $tournament->setExpectedRoster($tournamentData['expected_roster'] ?? null);
                        $tournament->setParsedTeams($tournamentData['teams'] ?? null);
                        $prizepool_usd = null;
                        if ($tournamentData['prizepool']) {
                            $currency_name = preg_replace('/^[0-9, ]+/', '', $tournamentData['prizepool']);
                            $money = preg_replace('/\D/', '', $tournamentData['prizepool']);
                            $currency_code = self::CURRENCY_MAP[$currency_name] ?? null;
                            if ($currency_code and isset($jabkaCurrency['rates'][$currency_code])) {
                                $prizepool_usd = (string)($money * $jabkaCurrency['rates'][$currency_code]);
                                $io->info($tournamentData['prizepool'] .' converted to ' . $prizepool_usd .' USD');
                            } else {
                                $io->warning('Rate not found for currency ' . $currency_name);
                            }
                        }
                        $tournament->setPrizepool($prizepool_usd);
                        $tournament->setRegion($tournamentData['region'] ?? null);
                        $tournament->setSerieId($tournamentData['serie_id']);
                        $tournament->setSerie($tournamentData['serie'] ?? null);
                        $tournament->setTier($tournamentData['tier'] ?? null);
                        $tournament->setType($tournamentData['type'] ?? null);
                        $tournament->setWinnerId($tournamentData['winner_id'] ?? null);
                        $tournament->setWinnerType($tournamentData['winner_type'] ?? null);
                        $tournaments[] = $tournament;
                    }

                    foreach ($tournaments as $tournament) {
                        $this->entityManager->persist($tournament);
                    }

                    $this->entityManager->flush();
                    $this->entityManager->clear();

                    $totalSavedTournaments += count($tournaments);
                    $io->info(sprintf('Saved %d tournaments from page %d', count($tournaments), $pageNum));

                } catch (Exception $e) {
                    $io->error(sprintf('Error fetching page %d: %s', $pageNum, $e->getMessage()));
                }
            }

            if ($emptyResponses === self::CONCURRENT_REQUESTS) {
                $io->warning('No more data found, stopping.');
                $hasMoreData = false;
            }

            $page += self::CONCURRENT_REQUESTS;
            usleep((int)(self::REQUEST_DELAY * 1_000_000));
        }

        $io->success(sprintf('Fetching complete. Total tournaments saved: %d', $totalSavedTournaments));
        return Command::SUCCESS;
    }

    /**
     * @throws Exception
     */
    private function convertToDateTime(?string $dateString): ?DateTimeImmutable
    {
        return $dateString ? new DateTimeImmutable($dateString) : null;
    }
}
