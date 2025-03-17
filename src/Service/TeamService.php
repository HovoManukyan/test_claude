<?php

namespace App\Service;

use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class TeamService
{
    private EntityManagerInterface $entityManager;
    private TagAwareCacheInterface $cache;
    private ValidatorInterface $validator;

    public function __construct(
        EntityManagerInterface $entityManager,
        TagAwareCacheInterface $cache,
        ValidatorInterface $validator
    ) {
        $this->entityManager = $entityManager;
        $this->cache = $cache;
        $this->validator = $validator;
    }

    public function getAllTeams(int $page, int $limit, ?string $name, ?array $locales = []): array
    {
        $cacheKey = sprintf('teams_page_%d_limit_%d_name_%s_locales_%s',
            $page,
            $limit,
            $name ?? 'all',
            implode(',', $locales)
        );

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($page, $limit, $name, $locales) {
            $item->expiresAfter(600); // Кэш на 10 минут
            $item->tag('teams');

            $offset = ($page - 1) * $limit;

            $qb = $this->entityManager->getRepository(Team::class)->createQueryBuilder('t');

            if ($name) {
                $qb->andWhere('LOWER(t.name) LIKE LOWER(:name)')
                    ->setParameter('name', '%' . strtolower(trim($name)) . '%');
            }

            if (!empty($locales)) {
                $qb->andWhere('t.location IN (:locales)')
                    ->setParameter('locales', $locales);
            }

            $total = (clone $qb)
                ->select('COUNT(t.id)')
                ->getQuery()
                ->getSingleScalarResult();

            $teams = $qb
                ->orderBy('t.id', 'ASC')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();

            return [
                'total' => (int) $total,
                'pages' => $limit > 0 ? (int) ceil($total / $limit) : 1,
                'data' => $teams,
            ];
        });
    }

    public function getTeamById(int $id): ?Team
    {
        $cacheKey = 'team_' . $id;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($id) {
            $item->expiresAfter(300); // Кэш на 5 минут
            $item->tag('teams');

            return $this->entityManager->getRepository(Team::class)->find($id);
        });
    }

    public function getTeamBySlug(string $slug): ?Team
    {
        $cacheKey = 'team_slug_' . $slug;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($slug) {
            $item->expiresAfter(300); // Кэш на 5 минут
            $item->tag('teams');

            return $this->entityManager->getRepository(Team::class)->findOneBy(['slug' => $slug]);
        });
    }

    public function getTeamsBySlug(array $teamSlugs): array
    {
        $cacheKey = 'teams_slugs_' . implode('_', $teamSlugs);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($teamSlugs) {
            $item->expiresAfter(300); // Кэш на 5 минут
            $item->tag('teams');

            return $this->entityManager->getRepository(Team::class)->findBy(['slug' => $teamSlugs]);
        });
    }

    public function getRandomTeams(?int $limit = 12): array
    {
        $cacheKey = 'random_teams_' . $limit;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($limit) {
            $item->expiresAfter(300); // Кэш на 5 минут
            $item->tag('teams');

            $sql = 'SELECT * 
                FROM team 
                OFFSET floor(random() * (SELECT COUNT(*) FROM team)) 
                LIMIT :limit;';
            $stmt = $this->entityManager->getConnection()->prepare($sql);
            $stmt->bindValue('limit', $limit);
            $result = $stmt->executeQuery();
            $teams = $result->fetchAllAssociative(); // Возвращаем массив данных
            $where = [];
            foreach ($teams as $team){
                $where[] = $team['id'];
            }
            return $this->entityManager->getRepository(Team::class)
                ->createQueryBuilder('t')
                ->where('t.id IN (:ids)')
                ->setParameter('ids', $where)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        });
    }


    public function updateTeam(int $teamId, ?string $bio, ?array $socials): ?Team
    {
        $team = $this->entityManager->getRepository(Team::class)->find($teamId);
        if (!$team) {
            return null;
        }

        if ($bio !== null) {
            $team->setBio($bio);
        }

        if ($socials !== null) {
            $errors = $this->validateSocials($socials);

            if (!empty($errors)) {
                throw new \InvalidArgumentException(implode(', ', $errors));
            }

            $team->setSocials($socials);
        }

        $this->entityManager->flush();

        $this->cache->invalidateTags(['teams']);

        return $team;
    }

    private function validateSocials(array $socials): array
    {
        $constraint = new Assert\Collection([
            'vk' => new Assert\Optional([new Assert\Url()]),
            'tg' => new Assert\Optional([new Assert\Url()]),
            'twitter' => new Assert\Optional([new Assert\Url()])
        ]);

        $violations = $this->validator->validate($socials, $constraint);
        $errors = [];

        foreach ($violations as $violation) {
            $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
        }

        return $errors;
    }
}
