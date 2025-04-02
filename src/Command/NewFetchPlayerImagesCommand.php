<?php

namespace App\Command;

use App\Doctrine\Paginator;
use App\Repository\PlayerRepository;
use App\Service\PlayerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:pandascore:fetch:player-images',
    description: 'Download player avatars for players with image URLs',
)]
class NewFetchPlayerImagesCommand extends Command
{
    private const LIMIT = 100;

    public function __construct(
        private readonly PlayerRepository       $playerRepository,
        private readonly PlayerService          $playerService,
        private readonly EntityManagerInterface $entityManager,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Скачивание изображений игроков (только у кого image начинается с https://)');

        $page = 1;
        $totalProcessed = 0;
        $totalDownloaded = 0;

        $qb = $this->playerRepository->getPlayerImageQuerybuilder();

        $paginator = new Paginator($qb, self::LIMIT);

        while (true) {
            $players = $paginator->paginate($page)->getResults();

            if (count($players) === 0) {
                break;
            }

            foreach ($players as $player) {
                $totalProcessed++;

                if ($this->playerService->downloadPlayerImage($player)) {
                    $totalDownloaded++;
                }
            }

            $this->entityManager->flush();
            $this->entityManager->clear();
            gc_collect_cycles();

            $io->writeln("📦 Страница $page: обработано " . count($players) . " игроков. Всего: $totalProcessed, скачано: $totalDownloaded");

            $page++;
        }

        $io->success("✅ Завершено. Всего обработано: $totalProcessed, скачано: $totalDownloaded");

        return Command::SUCCESS;
    }
}
