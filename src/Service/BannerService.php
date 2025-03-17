<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Banner;
use App\Repository\BannerRepository;
use App\Value\PaginatedResult;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class BannerService
{
    /**
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     * @param BannerRepository $bannerRepository Banner repository
     * @param FileService $fileService File service
     * @param ValidatorInterface $validator Validator
     * @param TagAwareCacheInterface $cache Cache
     * @param LoggerInterface $logger Logger
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BannerRepository       $bannerRepository,
        private readonly FileService            $fileService,
        private readonly ValidatorInterface     $validator,
        private readonly TagAwareCacheInterface $cache,
        private readonly LoggerInterface        $logger,
    )
    {
    }

    /**
     * Create a new banner
     *
     * @param string $title Banner title
     * @param string $type Banner type (default or promo)
     * @param string $buttonText Button text
     * @param string|null $promoText Promotional text
     * @param string $buttonLink Button link URL
     * @param array $pages Pages where the banner appears
     * @param UploadedFile|null $image Banner image
     * @return Banner Created banner
     * @throws \InvalidArgumentException If validation fails
     */
    public function createBanner(
        string        $title,
        string        $type,
        string        $buttonText,
        ?string       $promoText,
        string        $buttonLink,
        array         $pages,
        ?UploadedFile $image = null
    ): Banner
    {
        // Validate banner data
        $this->validateBannerData($title, $type, $buttonText, $promoText, $buttonLink, $pages, $image);

        // Create new banner
        $banner = new Banner();
        $banner->setTitle($title);
        $banner->setType($type);
        $banner->setButtonText($buttonText);
        $banner->setPromoText($promoText);
        $banner->setButtonLink($buttonLink);
        $banner->setPages($pages);

        // Save to get an ID
        $this->entityManager->persist($banner);
        $this->entityManager->flush();

        // Upload image if provided
        if ($image) {
            $imagePath = $this->fileService->uploadBannerImage($image, $banner->getId());
            $banner->setImage($imagePath);
            $this->entityManager->flush();
        }

        // Invalidate cache
        $this->invalidateCache();

        return $banner;
    }

    /**
     * Update an existing banner
     *
     * @param Banner $banner Banner to update
     * @param string $title New title
     * @param string $type New type
     * @param string $buttonText New button text
     * @param string|null $promoText New promotional text
     * @param string $buttonLink New button link URL
     * @param array $pages New pages
     * @param UploadedFile|null $image New image
     * @return Banner Updated banner
     * @throws \InvalidArgumentException If validation fails
     */
    public function updateBanner(
        Banner        $banner,
        string        $title,
        string        $type,
        string        $buttonText,
        ?string       $promoText,
        string        $buttonLink,
        array         $pages,
        ?UploadedFile $image = null
    ): Banner
    {
        // Validate banner data
        $this->validateBannerData($title, $type, $buttonText, $promoText, $buttonLink, $pages, $image);

        // Update banner
        $banner->setTitle($title);
        $banner->setType($type);
        $banner->setButtonText($buttonText);
        $banner->setPromoText($promoText);
        $banner->setButtonLink($buttonLink);
        $banner->setPages($pages);

        // Update image if provided
        if ($image) {
            // Delete old image if exists
            if ($banner->getImage()) {
                $this->fileService->deleteFile($banner->getImage());
            }

            // Upload new image
            $imagePath = $this->fileService->uploadBannerImage($image, $banner->getId());
            $banner->setImage($imagePath);
        }

        // Save changes
        $this->entityManager->flush();

        // Invalidate cache
        $this->invalidateCache();

        return $banner;
    }

    /**
     * Delete a banner
     *
     * @param Banner $banner Banner to delete
     * @return void
     */
    public function deleteBanner(Banner $banner): void
    {
        // Delete image if exists
        if ($banner->getImage()) {
            $this->fileService->deleteFile($banner->getImage());
        }

        // Delete banner
        $this->entityManager->remove($banner);
        $this->entityManager->flush();

        // Invalidate cache
        $this->invalidateCache();
    }

    /**
     * Get all banners with pagination
     *
     * @param int $page Page number
     * @param int $limit Results per page
     * @return PaginatedResult Banners with pagination metadata
     */
    public function getAllBanners(int $page, int $limit): PaginatedResult
    {
        // Get paginated banners
        $result = $this->bannerRepository->findPaginated($page, $limit);

        // Return as value object
        return PaginatedResult::fromRepositoryResult($result, $page, $limit);
    }

    /**
     * Get a banner by ID
     *
     * @param int $id Banner ID
     * @return Banner|null Banner entity or null if not found
     */
    public function getBannerById(int $id): ?Banner
    {
        return $this->bannerRepository->find($id);
    }

    /**
     * Get a banner for a specific page
     *
     * @param string $page Page identifier
     * @return Banner|null Banner entity or null if not found
     */
    public function getBannerByPage(string $page): ?Banner
    {
        return $this->cache->get('banner_page_' . $page, function (ItemInterface $item) use ($page) {
            $item->expiresAfter(3600); // Cache for 1 hour
            $item->tag(['banners']);

            return $this->bannerRepository->findOneRandomByPage($page);
        });
    }

    /**
     * Validate banner data
     *
     * @param string $title Banner title
     * @param string $type Banner type
     * @param string $buttonText Button text
     * @param string|null $promoText Promotional text
     * @param string $buttonLink Button link URL
     * @param array $pages Pages where the banner appears
     * @param UploadedFile|null $image Banner image
     * @return void
     * @throws \InvalidArgumentException If validation fails
     */
    private function validateBannerData(
        string        $title,
        string        $type,
        string        $buttonText,
        ?string       $promoText,
        string        $buttonLink,
        array         $pages,
        ?UploadedFile $image = null
    ): void
    {
        $constraints = new Assert\Collection([
            'title' => [new Assert\NotBlank(), new Assert\Length(['max' => 255])],
            'type' => [
                new Assert\NotBlank(),
                new Assert\Choice(['choices' => ['default', 'promo']])
            ],
            'button_text' => [new Assert\NotBlank(), new Assert\Length(['max' => 255])],
            'promo_text' => [
                new Assert\Optional([new Assert\Type('string')])
            ],
            'button_link' => [new Assert\NotBlank(), new Assert\Url()],
            'pages' => [
                new Assert\NotBlank(),
                new Assert\All([
                    new Assert\Choice([
                        'choices' => ["player_detail", "player_list", "team_detail", "team_list"],
                        'message' => "Invalid page value. Allowed values: player_detail, player_list, team_detail, team_list."
                    ])
                ])
            ]
        ]);

        $data = [
            'title' => $title,
            'type' => $type,
            'button_text' => $buttonText,
            'promo_text' => $promoText,
            'button_link' => $buttonLink,
            'pages' => $pages
        ];

        $violations = $this->validator->validate($data, $constraints);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            throw new \InvalidArgumentException(implode(', ', $errors));
        }

        // Validate image if provided
        if ($image) {
            $imageConstraint = new Assert\Image([
                'maxSize' => '5M',
                'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                'mimeTypesMessage' => 'Allowed image types are jpeg, png, webp.',
            ]);

            $imageViolations = $this->validator->validate($image, $imageConstraint);

            if (count($imageViolations) > 0) {
                $imageErrors = [];
                foreach ($imageViolations as $violation) {
                    $imageErrors[] = $violation->getMessage();
                }
                throw new \InvalidArgumentException(implode(', ', $imageErrors));
            }
        }
    }

    /**
     * Invalidate banner cache
     */
    private function invalidateCache(): void
    {
        $this->cache->invalidateTags(['banners']);
    }
}