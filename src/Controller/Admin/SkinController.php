<?php

namespace App\Controller\Admin;

use App\Doctrine\Paginator;
use App\Entity\Skin;
use App\Repository\SkinRepository;
use App\Request\Skin\CreateSkinRequest;
use App\Request\Skin\SkinListRequest;
use App\Request\Skin\UpdateSkinRequest;
use App\Service\SkinService;
use App\Trait\HttpControllerTrait;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/skin')]
class SkinController extends AbstractController
{
    use HttpControllerTrait;

    #[Route('', methods: ['GET'])]
    public function getSkins(
        #[MapQueryString] SkinListRequest $request,
        SkinRepository                    $repository
    ): JsonResponse
    {
        $page = $request->getPage();
        $limit = $request->getLimit();
        $name = $request->getName();

        $qb = $repository->getSearchQueryBuilder($name);
        $paginator = new Paginator($qb, $limit);
        $results = $paginator->paginate($page)->getResults();

        return $this->successResponse([
            'data' => $results,
            'meta' => $paginator->getMetadata()
        ], ['paginator', 'skin:default']);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function getSkin(
        #[MapEntity(mapping: ['id' => 'id'])] Skin $skin,
    ): JsonResponse
    {
        return $this->successResponse([
            'skin' => $skin,
        ], ['skin:default']);
    }

    #[Route('/create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateSkinRequest $request,
        SkinService                            $skinService
    ): JsonResponse
    {
        $skin = $skinService->createSkin($request);
        return $this->successResponse([
            $skin,
        ], ['skin:default']);
    }

    #[Route('/update/{id}', methods: ['POST'])]
    public function update(
        #[MapRequestPayload] UpdateSkinRequest $request,
        Skin                                   $skin,
        SkinService                            $skinService
    ): JsonResponse
    {
        $updatedSkin = $skinService->updateSkin($skin, $request);
        return $this->successResponse([
            $updatedSkin,
        ], ['skin:default']);
    }

    #[Route('/delete/{id}', methods: ['DELETE'])]
    public function delete(
        Skin        $skin,
        SkinService $skinService
    ): JsonResponse
    {
        $skinService->deleteSkin($skin);
        return $this->successResponse([
            'message' => 'Skin deleted successfully'
        ]);
    }
}
