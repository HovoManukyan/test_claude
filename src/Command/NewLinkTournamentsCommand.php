<?php

namespace App\Command;

use App\Repository\PlayerRepository;
use App\Repository\TeamRepository;
use App\Repository\TournamentRepository;
use App\Service\TournamentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:link:tournaments',
    description: 'Линкует турниры с командами и игроками на основе expectedRoster',
)]
class NewLinkTournamentsCommand extends Command
{
    private const LIMIT = 1;

    public function __construct(
        private readonly TournamentRepository $tournamentRepository,
        private readonly TeamRepository $teamRepository,
        private readonly PlayerRepository $playerRepository,
        private readonly TournamentService $tournamentService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Линковка турниров с командами и игроками (expectedRoster)...');

        $page = 1;
        $linkedTournaments = 0;

        while (true) {
            $tournaments = $this->tournamentRepository->findAllWithExpectedRosterPaginated($page, self::LIMIT);

            if (count($tournaments) === 0) {
                break;
            }

            foreach ($tournaments as $tournament) {
                $this->tournamentService->linkTeamsAndPlayers($tournament);
                $linkedTournaments++;
            }

            $this->entityManager->flush();
            $this->entityManager->clear();
            gc_collect_cycles();

            $io->writeln("🔗 Страница $page: обработано " . count($tournaments) . " турниров. Всего линковано: $linkedTournaments");
            $io->writeln("💾 mem: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB");

            $page++;
        }

        $io->success("✅ Завершено. Всего турниров обработано: $linkedTournaments");

        return Command::SUCCESS;
    }
}
