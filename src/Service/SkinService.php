<?php

namespace App\Service;

use App\Entity\Player;
use App\Entity\Skin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class SkinService
{
    private EntityManagerInterface $entityManager;
    private TagAwareCacheInterface $cache;

    public function __construct(EntityManagerInterface $entityManager, TagAwareCacheInterface $cache)
    {
        $this->entityManager = $entityManager;
        $this->cache = $cache;
    }

    public function createSkin(string $name, string $color, ?int $imageId = null, ?string $skinLink = null, ?float $price = null): Skin
    {
        $skin = new Skin();
        $skin->setName($name);
        $skin->setColor($color);
        $skin->setImageId($imageId);
        $skin->setSkinLink($skinLink);
        $skin->setPrice($price);

        $this->entityManager->persist($skin);
        $this->entityManager->flush();

        $this->cache->invalidateTags(['skins']);
        return $skin;
    }

    public function updateSkin(Skin $skin, ?string $name, ?string $color, ?int $imageId, ?string $skinLink, ?float $price): Skin
    {
        if ($name !== null) {
            $skin->setName($name);
        }
        if ($color !== null) {
            $skin->setColor($color);
        }
        if ($imageId !== null) {
            $skin->setImageId($imageId);
        }
        if ($skinLink !== null) {
            $skin->setSkinLink($skinLink);
        }
        if ($price !== null) {
            $skin->setPrice($price);
        }

        $this->entityManager->flush();

        $this->invalidatePlayersWithSkin($skin);

        $this->cache->invalidateTags(['skins']);

        $this->cache->delete('skin_' . $skin->getId());

        return $skin;
    }


    public function deleteSkin(Skin $skin): void
    {
        $this->invalidatePlayersWithSkin($skin);

        $this->entityManager->remove($skin);
        $this->entityManager->flush();

        $this->cache->invalidateTags(['skins']);

        $this->cache->delete('skin_' . $skin->getId());
    }

    public function getAllSkins(int $page, int $limit, ?string $name = null): array
    {
        $offset = ($page - 1) * $limit;
        $qb = $this->entityManager->getRepository(Skin::class)->createQueryBuilder('s');

        $total = (clone $qb)
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $skins = $qb
            ->orderBy('s.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'data' => $skins,
            'total' => $total,
            'pages' => ceil($total / $limit),
        ];
    }

    public function getSkinById(int $id): ?Skin
    {
        return $this->entityManager->getRepository(Skin::class)->find($id);
    }


    private function invalidatePlayersWithSkin(Skin $skin): void
    {
        $players = $this->entityManager->getRepository(Player::class)
            ->createQueryBuilder('p')
            ->join('p.skins', 's')
            ->where('s.id = :skinId')
            ->setParameter('skinId', $skin->getId())
            ->getQuery()
            ->getResult();

        foreach ($players as $player) {
            $this->cache->delete('player_' . $player->getId());
        }

        $this->cache->invalidateTags(['players']);
    }

}
