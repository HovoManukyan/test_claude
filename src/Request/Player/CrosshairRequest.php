<?php

namespace App\Request\Player;

use Symfony\Component\Validator\Constraints as Assert;

class CrosshairRequest
{
    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    private int $crosshairId;

    #[Assert\Type('bool')]
    private ?bool $style = null;

    #[Assert\Type('numeric')]
    #[Assert\Range(min: 0.5, max: 25)]
    #[Assert\DivisibleBy(0.5)]
    private ?float $size = null;

    #[Assert\Type('numeric')]
    #[Assert\Range(min: 0.5, max: 20)]
    #[Assert\DivisibleBy(0.5)]
    private ?float $thickness = null;

    #[Assert\Type('bool')]
    private ?bool $tShape = null;

    #[Assert\Type('bool')]
    private ?bool $dot = null;

    #[Assert\Type('integer')]
    #[Assert\Range(min: -100, max: 100)]
    private ?int $gap = null;

    #[Assert\Type('integer')]
    #[Assert\Range(min: 0, max: 255)]
    private ?int $alpha = null;

    #[Assert\Choice([0, 1, 2, 3, 4, 5])]
    private ?int $color = null;

    #[Assert\Type('integer')]
    #[Assert\Range(min: 0, max: 255)]
    private ?int $colorR = null;

    #[Assert\Type('integer')]
    #[Assert\Range(min: 0, max: 255)]
    private ?int $colorG = null;

    #[Assert\Type('integer')]
    #[Assert\Range(min: 0, max: 255)]
    private ?int $colorB = null;

    public function getCrosshairId(): int
    {
        return $this->crosshairId;
    }

    public function setCrosshairId(int $crosshairId): void
    {
        $this->crosshairId = $crosshairId;
    }

    public function getStyle(): ?bool
    {
        return $this->style;
    }

    public function setStyle(?bool $style): void
    {
        $this->style = $style;
    }

    public function getSize(): ?float
    {
        return $this->size;
    }

    public function setSize(?float $size): void
    {
        $this->size = $size;
    }

    public function getThickness(): ?float
    {
        return $this->thickness;
    }

    public function setThickness(?float $thickness): void
    {
        $this->thickness = $thickness;
    }

    public function getTShape(): ?bool
    {
        return $this->tShape;
    }

    public function setTShape(?bool $tShape): void
    {
        $this->tShape = $tShape;
    }

    public function getDot(): ?bool
    {
        return $this->dot;
    }

    public function setDot(?bool $dot): void
    {
        $this->dot = $dot;
    }

    public function getGap(): ?int
    {
        return $this->gap;
    }

    public function setGap(?int $gap): void
    {
        $this->gap = $gap;
    }

    public function getAlpha(): ?int
    {
        return $this->alpha;
    }

    public function setAlpha(?int $alpha): void
    {
        $this->alpha = $alpha;
    }

    public function getColor(): ?int
    {
        return $this->color;
    }

    public function setColor(?int $color): void
    {
        $this->color = $color;
    }

    public function getColorR(): ?int
    {
        return $this->colorR;
    }

    public function setColorR(?int $colorR): void
    {
        $this->colorR = $colorR;
    }

    public function getColorG(): ?int
    {
        return $this->colorG;
    }

    public function setColorG(?int $colorG): void
    {
        $this->colorG = $colorG;
    }

    public function getColorB(): ?int
    {
        return $this->colorB;
    }

    public function setColorB(?int $colorB): void
    {
        $this->colorB = $colorB;
    }
}