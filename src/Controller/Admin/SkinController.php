<?php

namespace App\Controller\Admin;

use App\Entity\Skin;
use App\Service\SkinService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/skin')]
class SkinController extends AbstractController
{
    private SkinService $skinService;
    private SerializerInterface $serializer;

    public function __construct(SkinService $skinService, SerializerInterface $serializer)
    {
        $this->skinService = $skinService;
        $this->serializer = $serializer;
    }

    #[Route('', methods: ['GET'])]
    public function getSkins(Request $request): JsonResponse
    {
        $page = (int)$request->query->get('page', 1);
        $limit = (int)$request->query->get('limit', 10);
        $name = $request->query->get('name');

        $result = $this->skinService->getAllSkins($page, $limit, $name);

        return new JsonResponse([
            'data' => $this->serializer->normalize($result['data'], null, ['groups' => 'skin:list']),
            'meta' => [
                'total' => $result['total'],
                'page' => $page,
                'limit' => $limit,
                'pages' => $result['pages']
            ]
        ]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function getSkin(int $id): JsonResponse
    {
        $skin = $this->skinService->getSkinById($id);

        if (!$skin) {
            return new JsonResponse(['error' => 'Skin not found'], 404);
        }

        return new JsonResponse(
            $this->serializer->normalize($skin, null, ['groups' => 'skin:details'])
        );
    }

    #[Route('/create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $name = $data['name'] ?? null;
        $color = $data['color'] ?? null;
        $imageId = $data['image_id'] ?? null;
        $skinLink = $data['skin_link'] ?? null;
        $price = $data['price'] ?? null;

        if (!$name || !$color) {
            return new JsonResponse(['error' => 'Name and color are required'], 400);
        }

        $skin = $this->skinService->createSkin($name, $color, $imageId, $skinLink, $price);

        return new JsonResponse(
            $this->serializer->normalize($skin, null, ['groups' => 'skin:details'])
        );
    }

    #[Route('/update/{id}', methods: ['PATCH'])]
    public function update(Request $request, Skin $skin): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $name = $data['name'] ?? null;
        $color = $data['color'] ?? null;
        $imageId = $data['image_id'] ?? null;
        $skinLink = $data['skin_link'] ?? null;
        $price = $data['price'] ?? null;

        $updatedSkin = $this->skinService->updateSkin($skin, $name, $color, $imageId, $skinLink, $price);

        return new JsonResponse(
            $this->serializer->normalize($updatedSkin, null, ['groups' => 'skin:details'])
        );
    }

    #[Route('/delete/{id}', methods: ['DELETE'])]
    public function delete(Skin $skin): JsonResponse
    {
        $this->skinService->deleteSkin($skin);

        return new JsonResponse(['message' => 'Skin deleted successfully']);
    }
}
