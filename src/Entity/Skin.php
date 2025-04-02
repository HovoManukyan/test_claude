<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
class Skin
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['skin:default', 'skin:list', 'skin:details', 'player:admin:details', 'player:detail'])]
    private int $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['skin:default', 'skin:list', 'skin:details', 'player:admin:details', 'player:detail'])]
    private string $name;

    #[ORM\Column(type: 'string', length: 7)]
    #[Groups(['skin:default', 'skin:list', 'skin:details', 'player:admin:details', 'player:detail'])]
    private string $color;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['skin:default', 'skin:list', 'skin:details', 'player:admin:details', 'player:detail'])]
    private ?int $imageId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['skin:default', 'skin:list', 'skin:details', 'player:admin:details', 'player:detail'])]
    private ?string $skinLink = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['skin:default', 'skin:list', 'skin:details', 'player:admin:details', 'player:detail'])]
    private ?float $price = null;

    // Getters and setters
    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): self
    {
        $this->color = $color;
        return $this;
    }

    public function getImageId(): ?int
    {
        return $this->imageId;
    }

    public function setImageId(?int $imageId): self
    {
        $this->imageId = $imageId;
        return $this;
    }

    public function getSkinLink(): ?string
    {
        return $this->skinLink;
    }

    public function setSkinLink(?string $skinLink): self
    {
        $this->skinLink = $skinLink;
        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): self
    {
        $this->price = $price;
        return $this;
    }
}
