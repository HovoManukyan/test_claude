<?php

declare(strict_types=1);

namespace App\Request\Player;

use Symfony\Component\Validator\Constraints as Assert;

final class PlayerListRequest
{
    #[Assert\Positive]
    private ?string $page = null;

    #[Assert\Range(min: 1, max: 1000)]
    private ?string $limit = null;

    #[Assert\Choice(['0', '1'])]
    private ?string $hasCrosshair = null;

    #[Assert\All([
        new Assert\Type(type: 'string')
    ])]
    private ?array $teamSlugs = null;

    #[Assert\Length(max: 255)]
    private ?string $name = null;

    public function getPage(): int
    {
        if (!$this->page) {
            return 1;
        }

        return (int)$this->page;
    }

    public function setPage(?string $page): void
    {
        $this->page = $page;
    }

    public function getLimit(): int
    {
        if (!$this->limit) {
            return 10;
        }

        return (int)$this->limit;
    }

    public function setLimit(?string $limit): void
    {
        $this->limit = $limit;
    }

    public function getHasCrosshair(): ?bool
    {
        if (null === $this->hasCrosshair) {
            return null;
        }

        return $this->hasCrosshair;
    }

    public function setHasCrosshair(?string $hasCrosshair): void
    {
        $this->hasCrosshair = $hasCrosshair;
    }

    public function getTeamSlugs(): ?array
    {
        return $this->teamSlugs;
    }

    public function setTeamSlugs(?array $teamSlugs): void
    {
        $this->teamSlugs = $teamSlugs;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}