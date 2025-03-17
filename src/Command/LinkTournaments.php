<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Player;
use App\Entity\Team;
use App\Entity\Tournament;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:link-tournaments',
    description: 'Link players and teams to tournaments with batch processing'
)]
class LinkTournaments extends Command
{
    private const BATCH_SIZE = 10;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '2048M');
        $io = new SymfonyStyle($input, $output);

        $page = 0;
        $hasMoreData = true;
        $parsedTournaments = 0;
        $notFoundPlayers = 0;

        while ($hasMoreData) {
            $io->note(sprintf('Memory usage on page %s: %.2f MB', $page, memory_get_usage() / 1024 / 1024));

            $query = $this->entityManager->getRepository(Tournament::class)
                ->createQueryBuilder('t')
                ->setFirstResult($page * self::BATCH_SIZE)
                ->setMaxResults(self::BATCH_SIZE)
                ->getQuery();

            $tournaments = $query->getResult();
            if (count($tournaments) == 0){
                $hasMoreData = false;
            }
            foreach ($tournaments as $tournament) {
                $expectedRoasters = $tournament->getExpectedRoster();
                foreach ($expectedRoasters as $expectedRoaster) {
                    $teamId = $expectedRoaster['team']['id'];
                    $team = $this->entityManager->getRepository(Team::class)->findOneBy(['pandascore_id' => $teamId]);
                    if ($team){
                        $tournament->addTeam($team);
                    }

                    $players = $expectedRoaster['players'];
                    foreach ($players as $roasterPlayer) {
                        $playerId = $roasterPlayer['id'];
                        $player = $this->entityManager->getRepository(Player::class)->findOneBy(['pandascore_id' => $playerId]);
                        if ($player) {
                            $tournament->addPlayer($player);
                        } else {
                            $notFoundPlayers++;
                            $io->info('Player with pandascore id ' . $playerId . ' not found');
                        }
                        unset($player);
                    }
                    unset($players);
                }
                $parsedTournaments++;
                $this->entityManager->persist($tournament);
            }
            $this->entityManager->flush();
            $this->entityManager->getUnitOfWork()->clear();
            $this->entityManager->clear();
            unset($tournaments);
            unset($expectedRoasters);
            unset($players);
            unset($player);
            gc_collect_cycles();
            $page++;
            $io->success(sprintf('Parsed: %d tournaments', $parsedTournaments));
            $io->note(sprintf('Peak memory usage: %.2f MB', memory_get_peak_usage() / 1024 / 1024));
        }

        $io->note('$notFoundPlayers = ' . $notFoundPlayers);

        return Command::SUCCESS;
    }
}
