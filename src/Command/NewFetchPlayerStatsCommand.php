<?php

namespace App\Command;

use App\Repository\PlayerRepository;
use App\Service\Pandascore\Fetcher\PlayerStatsFetcher;
use App\Service\PlayerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:pandascore:fetch:player-stats',
    description: 'Fetch player statistics from PandaScore API',
)]
class NewFetchPlayerStatsCommand extends Command
{
    private const LIMIT = 10;

    public function __construct(
        private readonly PlayerRepository       $playerRepository,
        private readonly PlayerStatsFetcher     $fetcher,
        private readonly PlayerService          $playerService,
        private readonly EntityManagerInterface $entityManager,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Загрузка статистики игроков с PandaScore');

        $page = 1;
        $updated = 0;

        while (true) {
            $players = $this->playerRepository->findPaginated($page, self::LIMIT);
            if (count($players) === 0) {
                break;
            }

            $batchUpdated = 0;

            echo "scuka Игроков в батче: " . count($players) . "\n";
            foreach ($this->fetcher->fetchStats($players) as $player => $data) {
                if (!is_array($data)) {
                    continue;
                }

                $this->playerService->updatePlayerStats($player, $data);
                $this->entityManager->persist($player);
                $batchUpdated++;
                $updated++;
            }

            $this->entityManager->flush();
            $this->entityManager->clear();
            gc_collect_cycles();

            $io->writeln("✅ Обработан батч #$page. Обновлено: $batchUpdated игроков");
            $io->writeln("💾 mem: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB");

            $page++;
        }

        $io->success("✅ Завершено. Всего обновлено: $updated игроков");

        return Command::SUCCESS;
    }
}
