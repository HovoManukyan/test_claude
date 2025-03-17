<?php

namespace App\Service;

use App\Entity\Player;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class PandaScoreService
{
    private EntityManagerInterface $entityManager;
    private NormalizerInterface $normalizer;

    public function __construct(EntityManagerInterface $entityManager, NormalizerInterface $normalizer)
    {
        $this->entityManager = $entityManager;
        $this->normalizer = $normalizer;
    }

    public function getPlayerList(int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;

        $players = $this->entityManager->getRepository(Player::class)
            ->findBy([], null, $perPage, $offset);
        return $this->normalizer->normalize($players, null, ['groups' => 'player:list']);
    }
}
