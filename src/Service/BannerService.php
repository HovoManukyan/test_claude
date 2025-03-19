<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Banner;
use App\Repository\BannerRepository;
use App\Request\Banner\CreateBannerRequest;
use App\Request\Banner\UpdateBannerRequest;
use App\Service\Cache\CacheKeyFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Сервис для управления баннерами
 */
final class BannerService
{
    private const CACHE_TAG = 'banners';
    private const CACHE_TTL = 3600; // 1 час

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BannerRepository $bannerRepository,
        private readonly FileService $fileService,
        private readonly TagAwareCacheInterface $cache,
        private readonly CacheKeyFactory $cacheKeyFactory,
        private readonly LoggerInterface $logger,
        private readonly string $bannerImagesDir
    ) {
    }

    /**
     * Получает баннер для указанной страницы
     *
     * @param string $pageIdentifier Идентификатор страницы (player_list, team_detail, etc.)
     * @return Banner|null Баннер или null если не найден
     */
    public function getBannerForPage(string $pageIdentifier): ?Banner
    {
        $cacheKey = $this->cacheKeyFactory->bannerByPage($pageIdentifier);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($pageIdentifier) {
            $item->expiresAfter(self::CACHE_TTL);
            $item->tag([self::CACHE_TAG]);

            try {
                return $this->bannerRepository->findOneRandomByPage($pageIdentifier);
            } catch (\Exception $e) {
                $this->logger->error('Error finding banner for page', [
                    'page' => $pageIdentifier,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        });
    }

    /**
     * Создает новый баннер
     *
     * @param CreateBannerRequest $request Запрос на создание баннера (уже валидированный)
     * @return Banner Созданный баннер
     * @throws FileException Если загрузка изображения не удалась
     */
    public function createBanner(CreateBannerRequest $request): Banner
    {
        // Создание баннера
        $banner = new Banner();
        $banner->setTitle($request->title)
            ->setType($request->type)
            ->setButtonText($request->buttonText)
            ->setPromoText($request->promoText)
            ->setButtonLink($request->buttonLink)
            ->setPages($request->pages);

        // Сохранение для получения ID
        $this->entityManager->persist($banner);
        $this->entityManager->flush();

        // Загрузка изображения если предоставлено
        if ($request->image) {
            $imagePath = $this->uploadBannerImage($request->image, $banner->getId());
            $banner->setImage($imagePath);
            $this->entityManager->flush();
        }

        // Инвалидация кеша
        $this->invalidateCache();

        return $banner;
    }

    /**
     * Обновляет существующий баннер
     *
     * @param Banner $banner Баннер для обновления
     * @param UpdateBannerRequest $request Запрос на обновление баннера (уже валидированный)
     * @return Banner Обновленный баннер
     * @throws FileException Если загрузка изображения не удалась
     */
    public function updateBanner(Banner $banner, UpdateBannerRequest $request): Banner
    {
        // Обновление баннера
        $banner->setTitle($request->title)
            ->setType($request->type)
            ->setButtonText($request->buttonText)
            ->setPromoText($request->promoText)
            ->setButtonLink($request->buttonLink)
            ->setPages($request->pages);

        // Обновление изображения если предоставлено
        if ($request->image) {
            // Удаление старого изображения если существует
            if ($banner->getImage()) {
                $this->fileService->deleteFile($banner->getImage());
            }

            // Загрузка нового изображения
            $imagePath = $this->uploadBannerImage($request->image, $banner->getId());
            $banner->setImage($imagePath);
        }

        // Сохранение изменений
        $this->entityManager->flush();

        // Инвалидация кеша
        $this->invalidateCache();

        return $banner;
    }

    /**
     * Удаляет баннер
     *
     * @param Banner $banner Баннер для удаления
     * @return void
     */
    public function deleteBanner(Banner $banner): void
    {
        // Удаление изображения если существует
        if ($banner->getImage()) {
            $this->fileService->deleteFile($banner->getImage());
        }

        // Удаление баннера
        $this->entityManager->remove($banner);
        $this->entityManager->flush();

        // Инвалидация кеша
        $this->invalidateCache();
    }

    /**
     * Получает баннер по ID
     *
     * @param int $id ID баннера
     * @return Banner|null Баннер или null если не найден
     */
    public function getBannerById(int $id): ?Banner
    {
        $cacheKey = $this->cacheKeyFactory->bannerEntity($id);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($id) {
            $item->expiresAfter(self::CACHE_TTL);
            $item->tag([self::CACHE_TAG, 'banner_' . $id]);

            return $this->bannerRepository->find($id);
        });
    }

    /**
     * Загружает изображение баннера
     *
     * @param UploadedFile $image Загруженное изображение
     * @param int $bannerId ID баннера
     * @return string Путь к изображению
     * @throws FileException Если загрузка не удалась
     */
    private function uploadBannerImage(UploadedFile $image, int $bannerId): string
    {
        $targetDirectory = sprintf('%s/%d', $this->bannerImagesDir, $bannerId);

        return $this->fileService->uploadFile($image, $targetDirectory, 'banner');
    }

    /**
     * Инвалидирует кеш баннеров
     */
    private function invalidateCache(): void
    {
        $this->cache->invalidateTags([self::CACHE_TAG]);
    }
}