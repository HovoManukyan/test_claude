<?php

namespace App\Command;

use App\Service\Pandascore\Fetcher\PlayerFetcher;
use App\Service\PlayerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:pandascore:fetch:players',
    description: 'Fetch all CS2 players from PandaScore API and save to DB',
)]
class NewFetchPlayersCommand extends Command
{
    public function __construct(
        private readonly PlayerFetcher $fetcher,
        private readonly PlayerService $playerService,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Загрузка игроков из PandaScore...');

        $processed = 0;

        try {
            $this->fetcher->fetchAllPages(function (array $players) use (&$processed, $io) {
                $this->playerService->syncBatchFromApi($players);
                $this->entityManager->flush();
                $this->entityManager->clear();
                gc_collect_cycles();
                $processed += count($players);
                $io->writeln("📦 Обработано ещё " . count($players) . " игроков. Всего: $processed");
            });
            $io->success("✅ Завершено. Всего загружено: $processed");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error("❌ Ошибка: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
