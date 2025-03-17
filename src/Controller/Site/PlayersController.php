<?php

namespace App\Controller\Site;

use App\DTO\BannerDTO;
use App\DTO\PlayerDTO;
use App\DTO\PlayerListDTO;
use App\DTO\TeamSelectedFilterDTO;
use App\Service\BannerService;
use App\Service\PlayerService;
use App\Service\TeamService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/players')]
class PlayersController extends AbstractController
{
    private PlayerService $playerService;
    private BannerService $bannerService;
    private SerializerInterface $serializer;
    private TeamService $teamService;

    public function __construct(PlayerService $playerService, BannerService $bannerService, TeamService $teamService, SerializerInterface $serializer)
    {
        $this->playerService = $playerService;
        $this->teamService = $teamService;
        $this->bannerService = $bannerService;
        $this->serializer = $serializer;
    }

    #[Route('/', methods: ['GET'])]
    public function listPlayers(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $hasCrosshair = $request->query->getBoolean('has_crosshair', false);

        $teamSlugs = $request->query->all('teams');
        $name = $request->query->get('name');

        $result = $this->playerService->getAllPlayers($page, $limit, $hasCrosshair ?: null, $teamSlugs, $name);

        $banner = $this->bannerService->getBannerByPage('player_list');
        $teams = null;
        if ($teamSlugs) {
            $teams = $this->teamService->getTeamsBySlug($teamSlugs);
        }

        $data = [
            'data' => array_map(fn($player) => new PlayerListDTO($player), (array)$result['data']),
            'banner' => $banner ? new BannerDTO($banner):null,
            'meta' => [
                'total' => $result['total'],
                'page' => $page,
                'limit' => $limit,
                'pages' => $result['pages']
            ],
            'selected_filter' => $teams ? array_map(fn($team) => new TeamSelectedFilterDTO($team), $teams) : null
        ];

        $response = new JsonResponse($data);

        $response->setPublic();
        $response->setMaxAge(600);
        $response->setSharedMaxAge(600);

        $etag = md5(json_encode($data));
        $response->setEtag($etag);

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

    #[Route('/{slug}', methods: ['GET'])]
    public function getPlayer(string $slug, Request $request): JsonResponse
    {
        $player = $this->playerService->getPlayerBySlug($slug);
        if (!$player) {
            return new JsonResponse(['error' => 'Player not found'], Response::HTTP_NOT_FOUND);
        }

        $banner = $this->bannerService->getBannerByPage('player_detail');
        $player = new PlayerDTO($player);

        $data = [
            'player' => $player,
            'banner' => $banner ? new BannerDTO($banner):null
        ];

        $response = new JsonResponse($data);

        $response->setPublic();
        $response->setMaxAge(600);
        $response->setSharedMaxAge(600);

        $etag = md5(json_encode($data));
        $response->setEtag($etag);

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

}
