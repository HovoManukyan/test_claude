<?php

declare(strict_types=1);

namespace App\Request\Skin;

use Symfony\Component\Validator\Constraints as Assert;

final class SkinListRequest
{
    #[Assert\Positive]
    private ?string $page = null;

    #[Assert\Range(min: 1, max: 1000)]
    private ?string $limit = null;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}