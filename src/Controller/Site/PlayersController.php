<?php

namespace App\Controller\Site;

use App\DTO\BannerDTO;
use App\DTO\PlayerDTO;
use App\DTO\PlayerListDTO;
use App\DTO\TeamSelectedFilterDTO;
use App\Repository\PlayerRepository;
use App\Service\BannerService;
use App\Service\Cache\CacheService;
use App\Service\HttpCacheService;
use App\Service\TeamService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use App\Request\PlayerListRequest;

#[Route('/players')]
class PlayersController extends AbstractController
{
    public function __construct(
        private readonly PlayerRepository $playerRepository,
        private readonly BannerService $bannerService,
        private readonly TeamService $teamService,
        private readonly SerializerInterface $serializer,
        private readonly CacheService $cacheService,
        private readonly HttpCacheService $httpCacheService
    ) {
    }

    #[Route('/', methods: ['GET'])]
    public function listPlayers(
        #[MapQueryString] PlayerListRequest $request
    ): JsonResponse {
        // Use cacheService to get or compute the response
        $data = $this->cacheService->getPlayerList(
            $request->page,
            $request->limit,
            $request->hasCrosshair,
            $request->teamSlugs,
            $request->name,
            function() use ($request) {
                // Get paginated players with filters
                $result = $this->playerRepository->findPaginated(
                    $request->page,
                    $request->limit,
                    $request->hasCrosshair,
                    $request->teamSlugs,
                    $request->name
                );

                // Get banner for the player list page
                $banner = $this->bannerService->getBannerByPage('player_list');

                // Get filtered teams data if team slugs provided
                $teams = null;
                if (!empty($request->teamSlugs)) {
                    $teams = $this->teamService->getTeamsBySlug($request->teamSlugs);
                }

                // Transform to DTOs
                return [
                    'data' => array_map(fn($player) => new PlayerListDTO($player), $result['data']),
                    'banner' => $banner ? new BannerDTO($banner) : null,
                    'meta' => [
                        'total' => $result['total'],
                        'page' => $request->page,
                        'limit' => $request->limit,
                        'pages' => $result['pages']
                    ],
                    'selected_filter' => $teams ? array_map(
                        fn($team) => new TeamSelectedFilterDTO($team),
                        $teams
                    ) : null
                ];
            }
        );

        // Create response
        $response = new JsonResponse($data);

        // Add cache headers
        $etag = $this->httpCacheService->generateEtag($data);
        $this->httpCacheService->addPlayerCacheHeaders($response, $etag);

        return $response;
    }

    #[Route('/{slug}', methods: ['GET'])]
    public function getPlayer(string $slug, Request $request): JsonResponse
    {
        // Use cacheService to get or compute the response
        $data = $this->cacheService->getPlayerDetail($slug, function() use ($slug) {
            $player = $this->playerRepository->findOneBySlugWithRelations($slug);

            if (!$player) {
                throw $this->createNotFoundException('Player not found');
            }

            // Get banner for player detail page
            $banner = $this->bannerService->getBannerByPage('player_detail');

            return [
                'player' => new PlayerDTO($player),
                'banner' => $banner ? new BannerDTO($banner) : null
            ];
        });

        // Handle case where player was not found in the callback
        if (!isset($data['player'])) {
            throw $this->createNotFoundException('Player not found');
        }

        // Create response
        $response = new JsonResponse($data);

        // Add cache headers
        $etag = $this->httpCacheService->generateEtag($data);
        $this->httpCacheService->addPlayerCacheHeaders($response, $etag);

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }
}