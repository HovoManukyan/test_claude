<?php

declare(strict_types=1);

namespace App\Controller\Site;

use App\Repository\TeamRepository;
use App\Request\Team\TeamDetailRequest;
use App\Request\Team\TeamListRequest;
use App\Response\Team\TeamDetailResponse;
use App\Response\Team\TeamListResponse;
use App\Service\BannerService;
use App\Service\Cache\CacheKeyFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/teams', name: 'app_teams_')]
final class TeamsController extends AbstractController
{
    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly BannerService $bannerService,
        private readonly TagAwareCacheInterface $cache,
        private readonly CacheKeyFactory $cacheKeyFactory,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function listTeams(
        #[MapQueryString] TeamListRequest $request
    ): JsonResponse {
        $cacheKey = $this->cacheKeyFactory->teamList(
            $request->page,
            $request->limit,
            $request->name,
            $request->locale
        );

        $data = $this->cache->get($cacheKey, function (ItemInterface $item) use ($request) {
            $item->expiresAfter(600);
            $item->tag(['teams', 'team_list']);

            // Получаем команды с пагинацией
            $paginatedTeams = $this->teamRepository->findPaginatedWithRelations(
                $request->page,
                $request->limit,
                $request->name,
                $request->locale
            );

            // Получаем баннер для страницы списка команд
            $banner = $this->bannerService->getBannerForPage('team_list');

            // Создаем объект ответа
            return TeamListResponse::fromPaginator(
                $paginatedTeams,
                $request->page,
                $request->limit,
                $banner
            );
        });

        return $this->json($data);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function getTeam(
        string $slug,
        #[MapQueryString] TeamDetailRequest $request
    ): JsonResponse {
        $cacheKey = $this->cacheKeyFactory->teamDetail($slug);

        $data = $this->cache->get($cacheKey, function (ItemInterface $item) use ($slug) {
            $item->expiresAfter(600);
            $item->tag(['teams', 'team_' . $slug]);

            // Получаем команду со всеми связями
            $team = $this->teamRepository->findBySlugWithRelations($slug);

            if (!$team) {
                throw $this->createNotFoundException('Team not found');
            }

            // Получаем баннер для страницы детальной информации
            $banner = $this->bannerService->getBannerForPage('team_detail');

            // Получаем случайные команды для рекомендаций
            $otherTeams = $this->teamRepository->findRandom(12);

            // Фильтруем текущую команду из рекомендаций
            $otherTeams = array_filter(
                $otherTeams,
                fn($otherTeam) => $otherTeam->getId() !== $team->getId()
            );

            // Создаем объект ответа
            return TeamDetailResponse::fromEntities(
                $team,
                $banner,
                array_values($otherTeams)
            );
        });

        return $this->json($data);
    }
}