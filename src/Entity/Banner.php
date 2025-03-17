<?php

namespace App\Entity;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
class Banner
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    #[Groups(["banner:list", "banner:details"])]
    private int $id;

    #[ORM\Column(type: "string", length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ["default", "promo"], message: "Invalid type. Allowed values: default, promo.")]
    #[Groups(["banner:list", "banner:details"])]
    private string $type;

    #[ORM\Column(type: "string", length: 255)]
    #[Assert\NotBlank]
    #[Groups(["banner:list", "banner:details"])]
    private string $title;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    #[Groups(["banner:list", "banner:details"])]
    private ?string $image = null;

    #[ORM\Column(type: "string", length: 255)]
    #[Assert\NotBlank]
    #[Groups(["banner:list", "banner:details"])]
    private string $buttonText;

    #[ORM\Column(type: "text", nullable: true)]
    #[Groups(["banner:details"])]
    private ?string $promoText = null;

    #[ORM\Column(type: "string", length: 255)]
    #[Assert\NotBlank]
    #[Groups(["banner:list", "banner:details"])]
    private string $buttonLink;

    #[ORM\Column(type: "json", options: ["jsonb" => true])]
    #[Assert\All([
        new Assert\Choice(choices: ["player_detail", "player_list", "team_detail", "team_list"])
    ])]
    #[Groups(["banner:list", "banner:details"])]
    private array $pages = [];

    #[ORM\Column(type: "datetime")]
    #[Groups(["banner:list", "banner:details"])]
    private DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    public function getButtonText(): string
    {
        return $this->buttonText;
    }

    public function setButtonText(string $buttonText): self
    {
        $this->buttonText = $buttonText;
        return $this;
    }

    public function getPromoText(): ?string
    {
        return $this->promoText;
    }

    public function setPromoText(?string $promoText): self
    {
        $this->promoText = $promoText;
        return $this;
    }

    public function getButtonLink(): string
    {
        return $this->buttonLink;
    }

    public function setButtonLink(string $buttonLink): self
    {
        $this->buttonLink = $buttonLink;
        return $this;
    }

    public function getPages(): array
    {
        return $this->pages;
    }

    public function setPages(array $pages): self
    {
        $this->pages = $pages;
        return $this;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }
}
