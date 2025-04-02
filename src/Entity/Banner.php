<?php

namespace App\Entity;

use App\Repository\BannerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BannerRepository::class)]
#[ORM\Table(name: 'banner')]
#[ORM\HasLifecycleCallbacks]
class Banner extends AbstractEntity
{
    /**
     * Valid banner types
     */
    public const TYPE_DEFAULT = 'default';
    public const TYPE_PROMO = 'promo';

    /**
     * Valid page identifiers
     */
    public const PAGE_PLAYER_DETAIL = 'player_detail';
    public const PAGE_PLAYER_LIST = 'player_list';
    public const PAGE_TEAM_DETAIL = 'team_detail';
    public const PAGE_TEAM_LIST = 'team_list';

    /**
     * @var int Banner ID
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['banner:default'])]
    private int $id;

    /**
     * @var string Banner type (default or promo)
     */
    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [self::TYPE_DEFAULT, self::TYPE_PROMO], message: 'Invalid type. Allowed values: default, promo.')]
    #[Groups(['banner:default'])]
    private string $type;

    /**
     * @var string Banner title
     */
    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Groups(['banner:default'])]
    private string $title;

    /**
     * @var string|null Banner image URL
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['banner:default'])]
    private ?string $image = null;

    /**
     * @var string Button text
     */
    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Groups(['banner:default'])]
    private string $buttonText;

    /**
     * @var string|null Promotional text
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['banner:default'])]
    private ?string $promoText = null;

    /**
     * @var string Button link URL
     */
    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Url]
    #[Groups(['banner:default'])]
    private string $buttonLink;

    /**
     * @var array Pages where the banner appears
     */
    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    #[Assert\NotBlank]
    #[Assert\All([
        new Assert\Choice(choices: [
            self::PAGE_PLAYER_DETAIL,
            self::PAGE_PLAYER_LIST,
            self::PAGE_TEAM_DETAIL,
            self::PAGE_TEAM_LIST
        ], message: 'Invalid page value.')
    ])]
    #[Groups(['banner:default'])]
    private array $pages = [];

    /**
     * Get banner ID
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get banner type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set banner type
     */
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get banner title
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Set banner title
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Get banner image URL
     */
    public function getImage(): ?string
    {
        return $this->image;
    }

    /**
     * Set banner image URL
     */
    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    /**
     * Get button text
     */
    public function getButtonText(): string
    {
        return $this->buttonText;
    }

    /**
     * Set button text
     */
    public function setButtonText(string $buttonText): self
    {
        $this->buttonText = $buttonText;
        return $this;
    }

    /**
     * Get promotional text
     */
    public function getPromoText(): ?string
    {
        return $this->promoText;
    }

    /**
     * Set promotional text
     */
    public function setPromoText(?string $promoText): self
    {
        $this->promoText = $promoText;
        return $this;
    }

    /**
     * Get button link URL
     */
    public function getButtonLink(): string
    {
        return $this->buttonLink;
    }

    /**
     * Set button link URL
     */
    public function setButtonLink(string $buttonLink): self
    {
        $this->buttonLink = $buttonLink;
        return $this;
    }

    /**
     * Get pages where the banner appears
     */
    public function getPages(): array
    {
        return $this->pages;
    }

    /**
     * Set pages where the banner appears
     */
    public function setPages(array $pages): self
    {
        $this->pages = $pages;
        return $this;
    }

    /**
     * Check if the banner appears on a specific page
     */
    public function isVisibleOnPage(string $page): bool
    {
        return in_array($page, $this->pages);
    }
}