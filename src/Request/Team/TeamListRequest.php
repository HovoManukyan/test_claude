<?php

declare(strict_types=1);

namespace App\Request\Team;

use Symfony\Component\Validator\Constraints as Assert;

final class TeamListRequest
{
    #[Assert\Positive]
    public ?string $page = null;

    #[Assert\Range(min: 1, max: 1000)]
    public ?string $limit = null;

    #[Assert\Length(max: 255)]
    public ?string $name = null;

    #[Assert\All([
        new Assert\Type(type: 'string')
    ])]
    public ?array $locale = null;

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

    public function getLocale(): ?array
    {
        return $this->locale;
    }

    public function setLocale(?array $locale): void
    {
        $this->locale = $locale;
    }

}