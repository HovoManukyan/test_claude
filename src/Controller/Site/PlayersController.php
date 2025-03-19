<?php

declare(strict_types=1);

namespace App\Controller\Site;

use App\Repository\PlayerRepository;
use App\Request\Player\PlayerDetailRequest;
use App\Request\Player\PlayerListRequest;
use App\Response\Player\PlayerDetailResponse;
use App\Response\Player\PlayerListResponse;
use App\Service\BannerService;
use App\Service\Cache\CacheKeyFactory;
use App\Service\TeamService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/players')]
final class PlayersController extends AbstractController
{
    public function __construct(
        private readonly PlayerRepository $playerRepository,
        private readonly BannerService $bannerService,
        private readonly TeamService $teamService,
        private readonly TagAwareCacheInterface $cache,
        private readonly CacheKeyFactory $cacheKeyFactory,
    ) {
    }

    #[Route('/', methods: ['GET'])]
    public function listPlayers(
        #[MapQueryString] PlayerListRequest $request
    ): JsonResponse {
        $cacheKey = $this->cacheKeyFactory->playerList(
            $request->page,
            $request->limit,
            $request->hasCrosshair,
            $request->teamSlugs,
            $request->name
        );

        $data = $this->cache->get($cacheKey, function (ItemInterface $item) use ($request) {
            $item->expiresAfter(600);
            $item->tag(['players', 'player_list']);

            // Получаем игроков с пагинацией и связями
            $paginatedPlayers = $this->playerRepository->findPaginatedWithRelations(
                $request->page,
                $request->limit,
                $request->hasCrosshair,
                $request->teamSlugs,
                $request->name
            );

            // Получаем баннер для страницы списка игроков
            $banner = $this->bannerService->getBannerForPage('player_list');

            // Получаем команды для фильтрации, если указаны slugs
            $teams = !empty($request->teamSlugs)
                ? $this->teamService->getTeamsBySlug($request->teamSlugs)
                : null;

            // Создаем объект ответа
            return PlayerListResponse::fromPaginator(
                $paginatedPlayers,
                $request->page,
                $request->limit,
                $banner,
                $teams
            );
        });

        return $this->json($data);
    }

    #[Route('/{slug}', methods: ['GET'])]
    public function getPlayer(
        string $slug,
        #[MapQueryString] PlayerDetailRequest $request
    ): JsonResponse {
        $cacheKey = $this->cacheKeyFactory->playerDetail($slug);

        $data = $this->cache->get($cacheKey, function (ItemInterface $item) use ($slug) {
            $item->expiresAfter(600);
            $item->tag(['players', 'player_' . $slug]);

            // Получаем игрока со всеми связями
            $player = $this->playerRepository->findBySlugWithRelations($slug);

            if (!$player) {
                throw $this->createNotFoundException('Player not found');
            }

            // Получаем баннер для страницы детальной информации
            $banner = $this->bannerService->getBannerForPage('player_detail');

            // Создаем объект ответа
            return PlayerDetailResponse::fromEntities($player, $banner);
        });

        return $this->json($data);
    }
}