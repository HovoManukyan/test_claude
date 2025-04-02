<?php

namespace App\Request\Team;

use App\Request\Player\CrosshairRequest;
use Symfony\Component\Validator\Constraints as Assert;

class TeamUpdateRequest
{

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


}