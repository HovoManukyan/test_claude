<?php

namespace App\Service;

use App\Entity\CronState;
use Doctrine\ORM\EntityManagerInterface;

class CronStateService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getCurrentState(): CronState
    {
        $state = $this->entityManager->getRepository(CronState::class)->find(1);

        if (!$state) {
            $state = new CronState();
            $this->entityManager->persist($state);
            $this->entityManager->flush();
        }

        return $state;
    }

    public function canRun(): bool
    {
//        $state = $this->getCurrentState();
//        $lastRunAt = $state->getLastRunAt();
//        $interval = $state->getInterval();
//
//        if (!$lastRunAt) {
//            return true;
//        }
//
//        $nextRunAt = (clone $lastRunAt)->modify("+{$interval} seconds");
//
//        return new \DateTime() >= $nextRunAt;
        return  true;
    }

    public function updateRunState(int $newPage): void
    {
        $state = $this->getCurrentState();
        $state->setCurrentPage($newPage);
        $state->setNextRunAt(new \DateTime());
        $this->entityManager->flush();
    }
}
