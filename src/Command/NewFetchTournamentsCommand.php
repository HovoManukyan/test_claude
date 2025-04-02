<?php

namespace App\Command;

use App\Service\Pandascore\Fetcher\TournamentFetcher;
use App\Service\TournamentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:pandascore:fetch:tournaments',
    description: 'Fetch all CS2 tournaments from PandaScore API and save to DB',
)]
class NewFetchTournamentsCommand extends Command
{
    public function __construct(
        private readonly TournamentFetcher $fetcher,
        private readonly TournamentService $tournamentService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '512M');
        $io = new SymfonyStyle($input, $output);
        $io->title('Загрузка турниров из PandaScore...');

        $processed = 0;

        $this->fetcher->fetchAllPages(function (array $tournaments) use (&$processed, $io) {
            $this->tournamentService->syncBatchFromApi($tournaments);
            $this->entityManager->flush();
            $this->entityManager->clear();
            gc_collect_cycles();
            $processed += count($tournaments);
            $io->writeln("📦 Обработано ещё " . count($tournaments) . " турниров. Всего: $processed");
        });
        $io->success("✅ Завершено. Всего загружено: $processed турниров");
        return Command::SUCCESS;
    }
}
