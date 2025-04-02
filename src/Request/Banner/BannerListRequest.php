<?php

declare(strict_types=1);

namespace App\Request\Banner;

use Symfony\Component\Validator\Constraints as Assert;

final class BannerListRequest
{
    #[Assert\Positive]
    private ?string $page = null;

    #[Assert\Range(min: 1, max: 1000)]
    private ?string $limit = null;

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

}