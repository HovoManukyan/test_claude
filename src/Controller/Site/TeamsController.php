<?php

namespace App\Controller\Site;

use App\Repository\TeamRepository;
use App\Request\TeamListRequest;
use App\Response\BannerResponse;
use App\Response\TeamListResponse;
use App\Response\TeamResponse;
use App\Response\TeamShortResponse;
use App\Service\BannerService;
use App\Service\Cache\CacheService;
use App\Value\PaginatedResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/teams', name: 'app_teams_')]
class TeamController extends AbstractController
{
    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly BannerService $bannerService,
        private readonly CacheService $cacheService,
    ) {
    }

    /**
     * List teams with pagination and filtering
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function listTeams(
        #[MapQueryString] TeamListRequest $request
    ): JsonResponse {
        // Cache result using specific keys for this query
        $response = $this->cacheService->getTeamList(
            $request->page,
            $request->limit,
            $request->name,
            $request->locale,
            function() use ($request) {
                // Get teams with pagination
                $paginationResult = $this->teamRepository->findPaginated(
                    $request->page,
                    $request->limit,
                    $request->name,
                    $request->locale
                );

                // Create paginated result value object
                $paginatedResult = PaginatedResult::fromRepositoryResult(
                    $paginationResult,
                    $request->page,
                    $request->limit
                );

                // Get banner for team list page
                $banner = $this->bannerService->getBannerByPage('team_list');

                // Create response DTO
                return TeamListResponse::fromPaginatedResult($paginatedResult, $banner);
            }
        );

        return $this->json($response);
    }

    /**
     * Get a team by slug
     */
    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function getTeam(string $slug): JsonResponse
    {
        // Cache result using specific key for this team
        $response = $this->cacheService->getTeamDetail($slug, function() use ($slug) {
            // Get team by slug
            $team = $this->teamRepository->findOneBySlug($slug);

            if (!$team) {
                throw $this->createNotFoundException('Team not found');
            }

            // Get banner for team detail page
            $banner = $this->bannerService->getBannerByPage('team_detail');

            // Get random teams for recommendation
            $otherTeams = $this->teamRepository->findRandom(12);

            // Filter out the current team from recommendations
            $otherTeams = array_filter(
                $otherTeams,
                fn($otherTeam) => $otherTeam->getId() !== $team->getId()
            );

            // Create response data
            return [
                'team' => TeamResponse::fromEntity($team),
                'banner' => $banner ? BannerResponse::fromEntity($banner) : null,
                'otherTeams' => array_map(
                    fn($otherTeam) => TeamShortResponse::fromEntity($otherTeam),
                    array_values($otherTeams)
                ),
            ];
        });

        return $this->json($response);
    }
}