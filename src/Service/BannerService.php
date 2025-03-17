<?php

namespace App\Service;

use App\Entity\Banner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class BannerService
{
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;
    private TagAwareCacheInterface $cache;
    private string $uploadDirectory = __DIR__ . "/../../public/cdn";

    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator, TagAwareCacheInterface $cache)
    {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->cache = $cache;
    }

    public function createBanner(
        ?string $title,
        ?string $type,
        ?string $buttonText,
        ?string $promoText,
        ?string $buttonLink,
        ?array $pages,
        ?UploadedFile $image = null
    ): Banner {
        // Валидация
        $this->validateBannerData($title, $type, $buttonText, $promoText, $buttonLink, $pages, $image);

        $banner = new Banner();
        $banner->setTitle($title);
        $banner->setType($type);
        $banner->setButtonText($buttonText);
        $banner->setPromoText($promoText);
        $banner->setButtonLink($buttonLink);
        $banner->setPages($pages);

        $this->entityManager->persist($banner);
        $this->entityManager->flush();

        if ($image) {
            $imagePath = $this->uploadImage($image, $banner->getId());
            $banner->setImage($imagePath);
            $this->entityManager->flush();
        }

        return $banner;
    }

    public function updateBanner(
        Banner $banner,
        ?string $title,
        ?string $type,
        ?string $buttonText,
        ?string $promoText,
        ?string $buttonLink,
        ?array $pages,
        ?UploadedFile $image = null
    ): Banner {
        $this->validateBannerData($title, $type, $buttonText, $promoText, $buttonLink, $pages, $image);

        $banner->setTitle($title);
        $banner->setType($type);
        $banner->setButtonText($buttonText);
        $banner->setPromoText($promoText);
        $banner->setButtonLink($buttonLink);
        $banner->setPages($pages);

        if ($image) {
            $this->deleteOldImages($banner->getId());

            $imagePath = $this->uploadImage($image, $banner->getId());
            $banner->setImage($imagePath);
        }

        $this->entityManager->flush();

        $this->cache->delete('banner_' . $banner->getId());

        $this->cache->invalidateTags(['banners']);

        return $banner;
    }


    private function validateBannerData(
        ?string $title,
        ?string $type,
        ?string $buttonText,
        ?string $promoText,
        ?string $buttonLink,
        ?array $pages,
        ?UploadedFile $image = null
    ): void {
        $constraints = new Assert\Collection([
            'title' => [new Assert\NotBlank(), new Assert\Type('string')],
            'type' => [
                new Assert\NotBlank(),
                new Assert\Choice(['choices' => ['default', 'promo']])
            ],
            'button_text' => [new Assert\NotBlank(), new Assert\Type('string')],
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

    private function uploadImage(UploadedFile $image, int $bannerId): string
    {
        $directory = $this->uploadDirectory . '/banners/' . $bannerId;

        // Создать директорию, если её нет
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $filename = 'banner.' . $image->guessExtension();
        $image->move($directory, $filename);

        return '/cdn/banners/' . $bannerId . '/' . $filename;
    }

    private function deleteOldImages(string $directory): void
    {
        if (is_dir($directory)) {
            array_map('unlink', glob($directory . '/*'));
        }
    }

    public function getAllBanners(int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        $qb = $this->entityManager->getRepository(Banner::class)->createQueryBuilder('b');

        $total = (clone $qb)
            ->select('COUNT(b.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $banners = $qb
            ->orderBy('b.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'data' => $banners,
            'total' => $total,
            'pages' => ceil($total / $limit),
        ];
    }

    public function getBannerById(int $id): ?Banner
    {
        return $this->entityManager->getRepository(Banner::class)->find($id);
    }

    public function deleteBanner(Banner $banner): void
    {
        $directory = $this->uploadDirectory . '/banners/' . $banner->getId();

        $this->deleteOldImages($directory);

        if (is_dir($directory)) {
            rmdir($directory);
        }

        $this->entityManager->remove($banner);
        $this->entityManager->flush();
    }

    public function getBannerByPage(string $page): ?Banner
    {
        $cacheKey = 'banner_page_' . $page;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($page) {
            $item->expiresAfter(600);
            $item->tag('banners');

            $conn = $this->entityManager->getConnection();

            $sql = "SELECT id FROM banner 
                WHERE pages @> :page 
                ORDER BY RANDOM() 
                LIMIT 1";

            $stmt = $conn->prepare($sql);
            $stmt->bindValue('page', json_encode([$page])); // Привязываем параметр вручную
            $result = $stmt->executeQuery(); // Выполняем без аргументов

            $bannerId = $result->fetchOne(); // Получаем один результат

            if (!$bannerId) {
                return null;
            }

            return $this->entityManager->getRepository(Banner::class)->find($bannerId);
        });
    }







}
