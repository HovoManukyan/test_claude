<?php

namespace App\Command;

use App\Doctrine\Paginator;
use App\Repository\TeamRepository;
use App\Service\TeamService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:pandascore:fetch:team-images',
    description: 'Download team logos and update image paths',
)]
class NewFetchTeamImagesCommand extends Command
{
    private const LIMIT = 100;

    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly TeamService $teamService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Скачивание логотипов команд (где image начинается с https://)');

        $page = 1;
        $totalProcessed = 0;
        $totalDownloaded = 0;

        $qb = $this->teamRepository->getTeamImageQueryBuilder();
        $paginator = new Paginator($qb, self::LIMIT);

        while (true) {
            $teams = $paginator->paginate($page)->getResults();

            if (count($teams) === 0) {
                break;
            }

            foreach ($teams as $team) {
                $totalProcessed++;

                if ($this->teamService->downloadTeamImage($team)) {
                    $totalDownloaded++;
                }
            }

            $this->entityManager->flush();
            $this->entityManager->clear();
            gc_collect_cycles();

            $io->writeln("📦 Страница $page: обработано " . count($teams) . " команд. Всего: $totalProcessed, скачано: $totalDownloaded");
            $page++;
        }

        $io->success("✅ Завершено. Всего обработано: $totalProcessed, скачано: $totalDownloaded");

        return Command::SUCCESS;
    }
}
