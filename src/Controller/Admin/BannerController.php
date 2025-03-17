<?php

namespace App\Controller\Admin;

use App\Service\BannerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/banners')]
class BannerController extends AbstractController
{
    private BannerService $bannerService;
    private SerializerInterface $serializer;

    public function __construct(BannerService $bannerService, SerializerInterface $serializer)
    {
        $this->bannerService = $bannerService;
        $this->serializer = $serializer;
    }

    #[Route('/', methods: ['GET'])]
    public function getBanners(Request $request): JsonResponse
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = max(1, (int)$request->query->get('limit', 10));

        $result = $this->bannerService->getAllBanners($page, $limit);

        return new JsonResponse([
            'data' => $this->serializer->normalize($result['data'], null, ['groups' => 'banner:list']),
            'meta' => [
                'total' => $result['total'],
                'page' => $page,
                'limit' => $limit,
                'pages' => $result['pages']
            ]
        ]);
    }

    #[Route('/get/{id}', methods: ['GET'])]
    public function getOne(int $id): JsonResponse
    {
        $banner = $this->bannerService->getBannerById($id);
        if (!$banner) {
            return new JsonResponse(['error' => 'Banner not found'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($this->serializer->normalize($banner, null, ['groups' => 'banner:details']));
    }

    #[Route('/create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // Получаем данные из запроса
        $title = $request->request->get('title');
        $type = $request->request->get('type');
        $buttonText = $request->request->get('buttonText');
        $promoText = $request->request->get('promoText');
        $buttonLink = $request->request->get('buttonLink');
        $pages = $request->request->all('pages');
        $image = $request->files->get('image');

        try {
            $banner = $this->bannerService->createBanner(
                $title,
                $type,
                $buttonText,
                $promoText,
                $buttonLink,
                $pages,
                $image
            );

            return new JsonResponse(
                $this->serializer->normalize($banner, null, ['groups' => 'banner:details']),
                Response::HTTP_CREATED
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to create banner: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/update/{id}', methods: ['POST'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $banner = $this->bannerService->getBannerById($id);
        if (!$banner) {
            return new JsonResponse(['error' => 'Banner not found'], Response::HTTP_NOT_FOUND);
        }

        $title = $request->request->get('title', $banner->getTitle());
        $type = $request->request->get('type', $banner->getType());
        $buttonText = $request->request->get('buttonText', $banner->getButtonText());
        $promoText = $request->request->get('promoText', $banner->getPromoText());
        $buttonLink = $request->request->get('buttonLink', $banner->getButtonLink());
        $pages = $request->request->all('pages') ?: $banner->getPages();
        $image = $request->files->get('image');

        try {
            $updatedBanner = $this->bannerService->updateBanner(
                $banner,
                $title,
                $type,
                $buttonText,
                $promoText,
                $buttonLink,
                $pages,
                $image
            );

            return new JsonResponse(
                $this->serializer->normalize($updatedBanner, null, ['groups' => 'banner:details']),
                Response::HTTP_OK
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to update banner: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/delete/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $banner = $this->bannerService->getBannerById($id);

        if (!$banner) {
            return new JsonResponse(['error' => 'Banner not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->bannerService->deleteBanner($banner);
            return new JsonResponse(['message' => 'Banner deleted successfully'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to delete banner: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
