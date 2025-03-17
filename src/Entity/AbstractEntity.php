<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use DateTimeImmutable;

abstract class AbstractEntity
{
    #[ORM\Column(name: "created_at", type: "datetime_immutable")]
    protected DateTimeImmutable $createdAt;

    #[ORM\Column(name: "updated_at", type: "datetime_immutable", nullable: true)]
    protected ?DateTimeImmutable $updatedAt = null;

    /**
     * @return DateTimeImmutable
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Set updated at timestamp
     */
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Set created at timestamp
     */
    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new DateTimeImmutable();
    }
}