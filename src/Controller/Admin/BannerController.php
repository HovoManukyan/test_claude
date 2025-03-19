<?php

namespace App\Controller\Admin;

use App\Entity\Banner;
use App\Request\Banner\CreateBannerRequest;
use App\Request\Banner\UpdateBannerRequest;
use App\Service\BannerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/banners')]
final class BannerController extends AbstractController
{
    public function __construct(
        private readonly BannerService $bannerService
    ) {
    }

    #[Route('/', methods: ['GET'])]
    public function getBanners(Request $request): JsonResponse
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = max(1, (int)$request->query->get('limit', 10));

        $result = $this->bannerService->getAllBanners($page, $limit);

        return $this->json([
            'data' => $result['data'],
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
            return $this->json(['error' => 'Banner not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($banner);
    }

    #[Route('/create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateBannerRequest $request
    ): JsonResponse {
        try {
            $banner = $this->bannerService->createBanner($request);
            return $this->json($banner, Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Failed to create banner: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/update/{id}', methods: ['POST'])]
    public function update(
        Banner $banner,
        #[MapRequestPayload] UpdateBannerRequest $request
    ): JsonResponse {
        try {
            $updatedBanner = $this->bannerService->updateBanner($banner, $request);
            return $this->json($updatedBanner);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Failed to update banner: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/delete/{id}', methods: ['DELETE'])]
    public function delete(Banner $banner): JsonResponse
    {
        try {
            $this->bannerService->deleteBanner($banner);
            return $this->json(['message' => 'Banner deleted successfully']);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Failed to delete banner: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}