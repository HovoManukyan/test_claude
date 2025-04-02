<?php

namespace App\Controller\Admin;

use App\Doctrine\Paginator;
use App\Entity\Team;
use App\Repository\TeamRepository;
use App\Request\Team\TeamListRequest;
use App\Request\Team\TeamUpdateRequest;
use App\Service\TeamService;
use App\Trait\HttpControllerTrait;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/teams')]
class TeamController extends AbstractController
{
    use HttpControllerTrait;

    public function __construct(
        private readonly TeamService         $teamService,
        private readonly SerializerInterface $serializer,
    )
    {
    }

    #[Route('', methods: ['GET'])]
    public function getList(
        #[MapQueryString] TeamListRequest $request,
        TeamRepository                    $repository,
    ): JsonResponse
    {
        $page = $request->getPage();
        $limit = $request->getLimit();
        $name = $request->getName();
        $locale = $request->getLocale();

        $qb = $repository->getSearchQueryBuilder($name, $locale);
        $paginator = new Paginator($qb, $limit);
        $results = $paginator->paginate($page)->getResults();

        return $this->successResponse([
            'data' => $results,
            'meta' => $paginator->getMetadata()
        ], ['paginator', 'team:list']);
    }

    #[Route('/{slug}', methods: ['GET'])]
    public function getTeam(
        #[MapEntity(mapping: ['slug' => 'slug'])] Team $team
    ): JsonResponse
    {
        return $this->successResponse([
            $team,
        ], ['team:detail']);
    }

    #[Route('/update/{id}', name: 'update_team', methods: ['PATCH'])]
    public function updateTeam(
        #[MapEntity(mapping: ['id' => 'id'])] Team $team,
        #[MapRequestPayload] TeamUpdateRequest     $request,
        TeamService                                $teamService
    ): JsonResponse
    {
        $updatedTeam = $teamService->updateTeam($team, $request);
        return $this->successResponse([
            $updatedTeam,
        ], ['team:detail']);
    }
}
