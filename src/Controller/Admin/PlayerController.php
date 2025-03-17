<?php

namespace App\Controller\Admin;

use App\Service\PlayerService;
use Exception;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/players')]
class PlayerController extends AbstractController
{
    private PlayerService $playerService;
    private SerializerInterface $serializer;

    public function __construct(PlayerService $playerService, SerializerInterface $serializer)
    {
        $this->playerService = $playerService;
        $this->serializer = $serializer;
    }

    #[Route('', methods: ['GET'])]
    public function getPlayersForAdmin(Request $request): JsonResponse
    {
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 20);

        $filters = [
            'name' => $request->query->get('name'),
            'team' => $request->query->get('team'),
            'country' => $request->query->get('country'),
            'hasCrosshair' => $request->query->has('hasCrosshair')
                ? filter_var($request->query->get('hasCrosshair'), FILTER_VALIDATE_BOOLEAN)
                : null
        ];

        $result = $this->playerService->getAllPlayersForAdmin($page, $limit, $filters);

        return new JsonResponse([
            'data' => $this->serializer->normalize($result['players'], null, ['groups' => ['player:admin:list', 'player:details:team']]),
            'meta' => [
                'total' => $result['total'],
                'page' => $page,
                'limit' => $limit,
                'pages' => $limit > 0 ? ceil((int) $result['total'] / $limit) : 1,
            ]
        ]);
    }

    #[Route('/{slug}', methods: ['GET'])]
    public function getPlayer(string $slug): JsonResponse
    {
        $player = $this->playerService->getPlayerBySlug($slug);
        if (!$player) {
            return new JsonResponse(['error' => 'Player not found'], 404);
        }

        return new JsonResponse($this->serializer->normalize($player, null, ['groups' => 'player:admin:details']));
    }

    #[Route('/update/{id}', methods: ['PATCH'])]
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $updatedPlayer = $this->playerService->updatePlayer($id, $request->toArray());
            return $this->json($this->serializer->normalize($updatedPlayer, null, ['groups' => 'player:admin:details']));
        } catch (InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return $this->json(['error' => 'Something went wrong.', 'message' => $e->getMessage()], 500);
        }
    }
}
