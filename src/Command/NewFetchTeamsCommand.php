<?php

namespace App\Command;

use App\Service\Pandascore\Fetcher\TeamFetcher;
use App\Service\TeamService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:pandascore:fetch:teams',
    description: 'Fetch all CS2 teams from PandaScore API and save to DB',
)]
class NewFetchTeamsCommand extends Command
{
    public function __construct(
        private readonly TeamFetcher $fetcher,
        private readonly TeamService $teamService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Загрузка команд из PandaScore...');

        $processed = 0;

        try {
            $self = $this;
            $this->fetcher->fetchAllPages(function (array $teams) use (&$processed, $io, $self) {
                $self->teamService->syncBatchFromApi($teams);

                $self->entityManager->flush();
                $self->entityManager->clear();
                gc_collect_cycles();
                $processed += count($teams);
                $io->writeln("📦 Обработано ещё " . count($teams) . " команд. Всего: $processed");
            });
            $io->success("✅ Завершено. Всего загружено: $processed команд");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error("❌ Ошибка: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
