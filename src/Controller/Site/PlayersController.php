<?php

namespace App\Controller\Site;

use App\Doctrine\Paginator;
use App\Entity\Player;
use App\Repository\BannerRepository;
use App\Repository\PlayerRepository;
use App\Request\Player\PlayerListRequest;
use App\Trait\HttpControllerTrait;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/players')]
class PlayersController extends AbstractController
{
    use HttpControllerTrait;

    private const BANNER_FOR_LIST = 'player_list';
    private const BANNER_FOR_PLAYER = 'player_detail';

    #[Route('/', methods: ['GET'])]
    public function listPlayers(
        #[MapQueryString] PlayerListRequest $request,
        PlayerRepository                    $repository,
        BannerRepository                    $bannerRepository
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
        $banner = $bannerRepository->findOneRandomByPage(self::BANNER_FOR_LIST);

        return $this->successResponse([
            'data' => $results,
            'banner' => $banner,
            'meta' => $paginator->getMetadata()
        ], ['paginator', 'player:list', 'banner:default']);
    }

    #[Route('/{slug}', methods: ['GET'])]
    public function getPlayer(
        #[MapEntity(mapping: ['slug' => 'slug'])] Player $player,
        BannerRepository                                 $bannerRepository
    ): JsonResponse
    {
        $banner = $bannerRepository->findOneRandomByPage(self::BANNER_FOR_PLAYER);

        return $this->successResponse([
            'player' => $player,
            'banner' => $banner
        ], ['player:detail', 'banner:default']);
    }
}
