<?php

namespace App\Controller\Site;

use App\DTO\BannerDTO;
use App\DTO\PlayerDTO;
use App\DTO\TeamDTO;
use App\DTO\TeamListDTO;
use App\Service\BannerService;
use App\Service\TeamService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/teams')]
class TeamsController extends AbstractController
{
    private TeamService $teamService;
    private SerializerInterface $serializer;
    private BannerService $bannerService;

    public function __construct(TeamService $teamService, BannerService $bannerService, SerializerInterface $serializer)
    {
        $this->teamService = $teamService;
        $this->serializer = $serializer;
        $this->bannerService = $bannerService;
    }

    #[Route('/', methods: ['GET'])]
    public function listTeams(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        $name = $request->query->get('name');
        $locales = $request->query->all('locale');

        $result = $this->teamService->getAllTeams($page, $limit, $name, $locales);
        $banner = $this->bannerService->getBannerByPage('team_list');

        $data = [
            'data' => array_map(fn($team) => new TeamListDTO($team), (array)$result['data']),
            'banner' => $banner ? new BannerDTO($banner):null,
            'meta' => [
                'total' => $result['total'],
                'page' => $page,
                'limit' => $limit,
                'pages' => $result['pages']
            ]
        ];

        $response = new JsonResponse($data);

        $response->setPublic();
        $response->setMaxAge(600); // Кэш на 10 минут
        $response->setSharedMaxAge(600);

        $etag = md5(json_encode($data));
        $response->setEtag($etag);

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

    #[Route('/{slug}', methods: ['GET'])]
    public function getTeam(string $slug, Request $request): JsonResponse
    {
        $team = $this->teamService->getTeamBySlug($slug);
        if (!$team) {
            return new JsonResponse(['error' => 'Team not found'], Response::HTTP_NOT_FOUND);
        }
        $banner = $this->bannerService->getBannerByPage('team_detail');
        $team = new TeamDTO($team);

        $otherTeams = $this->teamService->getRandomTeams();
        $data = [
            'team' => $team,
            'otherTeams' => array_map(fn($team) => new TeamListDTO($team), (array)$otherTeams),
            'banner' => $banner ? new BannerDTO($banner):null
        ];

        $response = new JsonResponse($data);

        $response->setPublic();
        $response->setMaxAge(600);

        $etag = md5(json_encode($data));
        $response->setEtag($etag);

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }
}
