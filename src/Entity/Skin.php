<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
class Skin
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    #[Groups(["skin:list", "skin:details", "player:admin:details"])]
    private int $id;

    #[ORM\Column(type: "string", length: 255)]
    #[Groups(["skin:list", "skin:details", "player:admin:details"])]
    private string $name;

    #[ORM\Column(type: "string", length: 7)]
    #[Groups(["skin:list", "skin:details", "player:admin:details"])]
    private string $color;

    #[ORM\Column(type: "integer", nullable: true)]
    #[Groups(["skin:list", "skin:details", "player:admin:details"])]
    private ?int $image_id = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    #[Groups(["skin:list", "skin:details", "player:admin:details"])]
    private ?string $skin_link = null;

    #[ORM\Column(type: "float", nullable: true)]
    #[Groups(["skin:list", "skin:details", "player:admin:details"])]
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
        return $this->image_id;
    }

    public function setImageId(?int $image_id): self
    {
        $this->image_id = $image_id;
        return $this;
    }

    public function getSkinLink(): ?string
    {
        return $this->skin_link;
    }

    public function setSkinLink(?string $skin_link): self
    {
        $this->skin_link = $skin_link;
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
