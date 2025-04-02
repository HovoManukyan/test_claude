<?php

namespace App\Service;

use App\Entity\Player;
use App\Entity\Skin;
use App\Request\Skin\CreateSkinRequest;
use App\Request\Skin\UpdateSkinRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class SkinService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function createSkin(CreateSkinRequest $request): Skin
    {
        $skin = new Skin();
        $skin->setName($request->getName());
        $skin->setColor($request->getColor());
        $skin->setImageId($request->getImageId());
        $skin->setSkinLink($request->getSkinLink());
        $skin->setPrice($request->getPrice());

        $this->entityManager->persist($skin);
        $this->entityManager->flush();

        return $skin;
    }

    public function updateSkin(Skin $skin, UpdateSkinRequest $request): Skin
    {
        $skin->setName($request->getName())
            ->setSkinLink($request->getSkinLink())
            ->setImageId($request->getImageId())
            ->setColor($request->getColor())
            ->setPrice($request->getPrice());

        $this->entityManager->flush();

        return $skin;
    }


    public function deleteSkin(Skin $skin): void
    {

        $this->entityManager->remove($skin);
        $this->entityManager->flush();
    }

}
