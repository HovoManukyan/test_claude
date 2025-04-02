<?php

namespace App\Controller\Admin;

use App\Doctrine\Paginator;
use App\Entity\Banner;
use App\Repository\BannerRepository;
use App\Request\Banner\BannerListRequest;
use App\Request\Banner\CreateBannerRequest;
use App\Request\Banner\UpdateBannerRequest;
use App\Service\BannerService;
use App\Trait\HttpControllerTrait;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/banners')]
final class BannerController extends AbstractController
{
    use HttpControllerTrait;

    #[Route('/', methods: ['GET'])]
    public function getBanners(
        #[MapQueryString] BannerListRequest $request,
        BannerRepository                    $repository
    ): JsonResponse
    {
        $page = $request->getPage();
        $limit = $request->getLimit();
        $qb = $repository->getSearchQueryBuilder();
        $paginator = new Paginator($qb, $limit);
        $results = $paginator->paginate($page)->getResults();
        return $this->successResponse([
            'data' => $results,
            'meta' => $paginator->getMetadata()
        ], ['paginator', 'banner:default']);
    }

    #[Route('/get/{id}', methods: ['GET'])]
    public function getOne(
        #[MapEntity(mapping: ['id' => 'id'])] Banner $banner
    ): JsonResponse
    {
        return $this->successResponse([
            'banner' => $banner
        ], ['banner:default']);
    }

    #[Route('/create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateBannerRequest $request,
        BannerService                            $bannerService
    ): JsonResponse
    {
        $banner = $bannerService->createBanner($request);
        return $this->successResponse([
            $banner,
        ], ['banner:default']);
    }

    #[Route('/update/{id}', methods: ['POST'])]
    public function update(
        Banner                                   $banner,
        #[MapRequestPayload] UpdateBannerRequest $request,
        BannerService                            $bannerService
    ): JsonResponse
    {
        $updatedBanner = $bannerService->updateBanner($banner, $request);
        return $this->successResponse([
            $updatedBanner,
        ], ['banner:default']);
    }

    #[Route('/delete/{id}', methods: ['DELETE'])]
    public function delete(
        Banner        $banner,
        BannerService $bannerService
    ): JsonResponse
    {
        $bannerService->deleteBanner($banner);
        return $this->successResponse([
            'message' => 'Banner deleted successfully'
        ]);
    }
}