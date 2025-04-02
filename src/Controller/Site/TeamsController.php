<?php

declare(strict_types=1);

namespace App\Controller\Site;

use App\Doctrine\Paginator;
use App\Entity\Team;
use App\Repository\BannerRepository;
use App\Repository\TeamRepository;
use App\Request\Team\TeamListRequest;
use App\Service\BannerService;
use App\Service\Cache\CacheKeyFactory;
use App\Trait\HttpControllerTrait;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/teams', name: 'app_teams_')]
final class TeamsController extends AbstractController
{
    use HttpControllerTrait;

    private const BANNER_FOR_LIST = 'team_list';
    private const BANNER_FOR_PLAYER = 'team_detail';

    public function __construct(
        private readonly TeamRepository         $teamRepository,
        private readonly BannerService          $bannerService,
        private readonly TagAwareCacheInterface $cache,
        private readonly CacheKeyFactory        $cacheKeyFactory,
    )
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function listTeams(
        #[MapQueryString] TeamListRequest $request,
        TeamRepository                    $repository,
        BannerRepository                  $bannerRepository
    ): JsonResponse
    {
        $page = $request->getPage();
        $limit = $request->getLimit();
        $name = $request->getName();
        $locale = $request->getLocale();

        $qb = $repository->getSearchQueryBuilder($name, $locale);
        $paginator = new Paginator($qb, $limit);
        $results = $paginator->paginate($page)->getResults();
        $banner = $bannerRepository->findOneRandomByPage(self::BANNER_FOR_LIST);

        return $this->successResponse([
            'data' => $results,
            'banner' => $banner,
            'meta' => $paginator->getMetadata()
        ], ['paginator', 'team:list', 'banner:default']);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function getTeam(
        #[MapEntity(mapping: ['slug' => 'slug'])] Team $team,
        BannerRepository                               $bannerRepository
    ): JsonResponse
    {
        $banner = $bannerRepository->findOneRandomByPage(self::BANNER_FOR_PLAYER);

        return $this->successResponse([
            'team' => $team,
            'banner' => $banner
        ], ['team:detail', 'banner:default']);
    }
}