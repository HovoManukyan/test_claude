<?php

namespace App\Request\Player;

use Symfony\Component\Validator\Constraints as Assert;

class PlayerUpdateRequest
{
    #[Assert\Type('string')]
    private ?string $firstName = null;

    #[Assert\Type('string')]
    private ?string $lastName = null;

    #[Assert\Date]
    private ?string $birthday = null;

    #[Assert\Type('string')]
    private ?string $bio = null;

    #[Assert\Type('array')]
    #[Assert\Collection(
        fields: [
            'vk' => new Assert\Optional([new Assert\Url()]),
            'tg' => new Assert\Optional([new Assert\Url()]),
            'twitter' => new Assert\Optional([new Assert\Url()]),
            'instagram' => new Assert\Optional([new Assert\Url()]),
            'facebook' => new Assert\Optional([new Assert\Url()]),
            'youtube' => new Assert\Optional([new Assert\Url()]),
            'twitch' => new Assert\Optional([new Assert\Url()]),
            'discord' => new Assert\Optional([new Assert\Url()]),
        ],
        allowExtraFields: true
    )]
    private ?array $socials = null;

    #[Assert\Type('array')]
    private ?array $skins = null;

    #[Assert\Valid]
    private ?CrosshairRequest $crosshair = null;

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getBirthday(): ?string
    {
        return $this->birthday;
    }

    public function setBirthday(?string $birthday): void
    {
        $this->birthday = $birthday;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): void
    {
        $this->bio = $bio;
    }

    public function getSocials(): ?array
    {
        return $this->socials;
    }

    public function setSocials(?array $socials): void
    {
        $this->socials = $socials;
    }

    public function getSkins(): ?array
    {
        return $this->skins;
    }

    public function setSkins(?array $skins): void
    {
        $this->skins = $skins;
    }

    public function getCrosshair(): ?CrosshairRequest
    {
        return $this->crosshair;
    }

    public function setCrosshair(?CrosshairRequest $crosshair): void
    {
        $this->crosshair = $crosshair;
    }
}