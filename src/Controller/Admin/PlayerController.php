<?php

namespace App\Controller\Admin;

use App\Doctrine\Paginator;
use App\Entity\Player;
use App\Repository\PlayerRepository;
use App\Request\Player\PlayerListRequest;
use App\Request\Player\PlayerUpdateRequest;
use App\Service\PlayerService;
use App\Trait\HttpControllerTrait;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/players')]
class PlayerController extends AbstractController
{
    use HttpControllerTrait;

    #[Route('', methods: ['GET'])]
    public function getPlayersForAdmin(
        #[MapQueryString] PlayerListRequest $request,
        PlayerRepository                    $repository
    ): JsonResponse
    {
        $page = $request->getPage();
        $limit = $request->getLimit();
        $name = $request->getName();
        $teamSlugs = $request->getTeamSlugs();
        $hasCrosshair = $request->getHasCrosshair();

        $qb = $repository->getSearchQueryBuilder($hasCrosshair, $teamSlugs, $name);
        $paginator = new Paginator($qb, $limit);
        $results = $paginator->paginate($page)->getResults();
        return $this->successResponse([
            'data' => $results,
            'meta' => $paginator->getMetadata()
        ], ['paginator', 'player:list', 'banner:default']);
    }

    #[Route('/{slug}', methods: ['GET'])]
    public function getPlayer(
        #[MapEntity(mapping: ['slug' => 'slug'])] Player $player,
    ): JsonResponse
    {
        return $this->successResponse([
            'player' => $player,
        ], ['player:detail']);
    }

    #[Route('/update/{id}', name: 'update', methods: ['PATCH'])]
    public function updatePlayer(
        #[MapEntity(mapping: ['id' => 'id'])] Player $player,
        #[MapRequestPayload] PlayerUpdateRequest     $request,
        PlayerService                                $playerService
    ): JsonResponse
    {
        $updatedPlayer = $playerService->updatePlayer($player, $request);
        return $this->successResponse(
            $updatedPlayer,
            ['player:detail']
        );
    }
}
