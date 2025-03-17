<?php
namespace App\Controller\Admin;

use App\Service\TeamService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/teams')]
class TeamController extends AbstractController
{
    private TeamService $teamService;
    private SerializerInterface $serializer;

    public function __construct(TeamService $teamService, SerializerInterface $serializer)
    {
        $this->teamService = $teamService;
        $this->serializer = $serializer;
    }

    #[Route('', methods: ['GET'])]
    public function getList(Request $request): JsonResponse
    {
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 10);
        $location = $request->query->get('location');
        $name = $request->query->get('name');

        $result = $this->teamService->getAllTeamsForAdmin($page, $limit, $location, $name);

        return new JsonResponse([
            'data' => $this->serializer->normalize($result['data'], null, ['groups' => 'team:list']),
            'meta' => [
                'total' => $result['total'],
                'page' => $page,
                'limit' => $limit,
                'pages' => $result['pages']
            ]
        ]);
    }

    #[Route('/{slug}', methods: ['GET'])]
    public function getTeam(string $slug): JsonResponse
    {
        $team = $this->teamService->getTeamBySlug($slug);
        if (!$team) {
            return new JsonResponse(['error' => 'Team not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(
            $this->serializer->normalize($team, null, ['groups' => 'team:details']),
            Response::HTTP_OK
        );
    }

    #[Route('/update/{id}', name: 'update_team', methods: ['PATCH'])]
    public function updateTeam(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $bio = $data['bio'] ?? null;
        $socials = $data['socials'] ?? null;

        $team = $this->teamService->updateTeam($id, $bio, $socials);

        if (!$team) {
            return new JsonResponse(['error' => 'Team not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(
            $this->serializer->normalize($team, null, ['groups' => 'team:details']),
            Response::HTTP_OK
        );
    }
}
