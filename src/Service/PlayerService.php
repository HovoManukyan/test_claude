<?php

namespace App\Service;

use App\Entity\Player;
use App\Entity\Skin;
use DateTime;
use Exception;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class PlayerService
{
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;
    private TagAwareCacheInterface $cache;

    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator, TagAwareCacheInterface $cache)
    {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->cache = $cache;
    }



    public function getPlayerBySlug(string $slug): ?Player
    {
        $cacheKey = 'player_slug_' . $slug;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($slug) {
            $item->expiresAfter(300); // Кэш на 5 минут
            $item->tag('players');

            // Using repository with explicit joins to ensure all related entities are loaded
            $player = $this->entityManager->getRepository(Player::class)
                ->createQueryBuilder('p')
                ->leftJoin('p.teams', 't')
                ->leftJoin('p.currentTeam', 'ct')
                ->leftJoin('p.skins', 's')
                ->where('p.slug = :slug')
                ->setParameter('slug', $slug)
                ->getQuery()
                ->getOneOrNullResult();

            // Initialize all entity relations to prevent lazy loading issues after caching
            if ($player) {
                $this->initializePlayerRelations($player);
            }

            return $player;
        });
    }

    private function initializePlayerRelations(Player $player): void
    {
        // Initialize teams collection
        if ($player->getTeams()) {
            foreach ($player->getTeams() as $team) {
                // Access key properties to ensure initialization
                $team->getId();
                $team->getName();
                $team->getSlug();
                $team->getImage();
                $team->getPandascoreId(); // Ensure this property is initialized

                // Initialize any other Team properties that cause errors
                // For example, if there are other Team properties that need to be initialized:
                // $team->getOtherProperty();
            }
        }

        // Initialize current team if present
        if ($player->getCurrentTeam()) {
            $player->getCurrentTeam()->getId();
            $player->getCurrentTeam()->getName();
            $player->getCurrentTeam()->getSlug();
            $player->getCurrentTeam()->getImage();
            $player->getCurrentTeam()->getPandascoreId(); // Ensure this property is initialized
        }

        // Initialize skins collection
        if ($player->getSkins()) {
            foreach ($player->getSkins() as $skin) {
                $skin->getId();
                // Initialize any other Skin properties that are needed
            }
        }

        // Add any other relations that need to be initialized
    }

    public function updatePlayer(int $id, array $data): Player
    {
        $player = $this->entityManager->getRepository(Player::class)->find($id);

        if (!$player) {
            throw new \InvalidArgumentException('Player not found.');
        }

        if (array_key_exists('crosshair', $data)) {
            if ($data['crosshair'] === null) {
                $player->setCrosshair(null);
            } else {
                $violations = $this->validateCrosshair($data['crosshair']);
                if (!empty($violations)) {
                    throw new \InvalidArgumentException(implode(', ', $violations));
                }
                $player->setCrosshair($data['crosshair']);
            }
        }

        if (isset($data['firstName'])) {
            $player->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $player->setLastName($data['lastName']);
        }

        if (isset($data['birthday'])) {
            $player->setBirthday(new DateTime($data['birthday']));
        }

        if (isset($data['bio'])) {
            $player->setBio($data['bio']);
        }

        if (isset($data['socials']) && is_array($data['socials'])) {
            $player->setSocials($data['socials']);
        }

        if (isset($data['skins']) && is_array($data['skins'])) {
            $player->getSkins()->clear();
            foreach ($data['skins'] as $skinId) {
                $skin = $this->entityManager->getRepository(Skin::class)->find($skinId);
                if ($skin) {
                    $player->addSkin($skin);
                }
            }
        }

        $this->entityManager->flush();

        $this->cache->invalidateTags(['players', 'admin_players']);

        $this->cache->delete('player_' . $id);
        if ($player->getSlug()) {
            $this->cache->delete('player_slug_' . $player->getSlug());
        }

        return $player;
    }

    public function validateCrosshair(?array $crosshair): array
    {
        if ($crosshair === null) {
            return [];
        }

        $constraints = new Assert\Collection([
            'crosshairId' => [
                new Assert\NotBlank(['allowNull' => true]),
                new Assert\Type(['type' => ['integer', 'null']])
            ],
            'style' => [
                new Assert\Type('bool')
            ],
            'size' => [
                new Assert\Type('numeric'),
                new Assert\Range(['min' => 0.5, 'max' => 25]),
                new Assert\DivisibleBy(0.5)
            ],
            'thickness' => [
                new Assert\Type('numeric'),
                new Assert\Range(['min' => 0.5, 'max' => 20]),
                new Assert\DivisibleBy(0.5)
            ],
            'tShape' => [
                new Assert\Type('bool')
            ],
            'dot' => [
                new Assert\Type('bool')
            ],
            'gap' => [
                new Assert\Type('integer'),
                new Assert\Range(['min' => -100, 'max' => 100])
            ],
            'alpha' => [
                new Assert\Type('integer'),
                new Assert\Range(['min' => 0, 'max' => 255])
            ],
            'color' => [
                new Assert\Choice([0, 1, 2, 3, 4, 5])
            ],
            'colorR' => [
                new Assert\Optional([
                    new Assert\Type('integer'),
                    new Assert\Range(['min' => 0, 'max' => 255])
                ])
            ],
            'colorG' => [
                new Assert\Optional([
                    new Assert\Type('integer'),
                    new Assert\Range(['min' => 0, 'max' => 255])
                ])
            ],
            'colorB' => [
                new Assert\Optional([
                    new Assert\Type('integer'),
                    new Assert\Range(['min' => 0, 'max' => 255])
                ])
            ],
        ]);

        $violations = $this->validator->validate($crosshair, $constraints);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            return $errors;
        }

        return [];
    }

    private function isValidFloatStep(float $value, float $min, float $max, float $step): bool
    {
        if ($value < $min || $value > $max) {
            return false;
        }

        return fmod($value - $min, $step) === 0.0;
    }

    /**
     * Получить список всех игроков с пагинацией и фильтрацией по прицелу
     *
     * @param int  $page         Номер страницы
     * @param int  $limit        Количество записей на страницу
     * @param bool $hasCrosshair Фильтр на наличие прицела
     *
     * @return Player[]
     */
    public function getAllPlayers(int $page, int $limit, ?bool $hasCrosshair = null, ?array $teamSlugs = [], ?string $name = null): array
    {
        // 🔥 Уникальный ключ кэша на основе параметров
        $cacheKey = sprintf(
            'players_page_%d_limit_%d_crosshair_%s_teamSlugs_%s_name_%s',
            $page,
            $limit,
            $hasCrosshair !== null ? (int) $hasCrosshair : 'all',
            implode(',', $teamSlugs),
            $name ?? 'all'
        );

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($page, $limit, $hasCrosshair, $teamSlugs, $name) {
            $item->expiresAfter(600); // Кэш на 10 минут
            $item->tag('players');

            $offset = ($page - 1) * $limit;

            $qb = $this->entityManager->getRepository(Player::class)->createQueryBuilder('p');

            if ($hasCrosshair !== null) {
                $qb->andWhere($hasCrosshair ? 'p.crosshair IS NOT NULL' : 'p.crosshair IS NULL');
            }

            if (!empty($teamSlugs)) {
                $qb->join('p.teams', 't')
                    ->andWhere('t.slug IN (:teamSlugs)')
                    ->setParameter('teamSlugs', $teamSlugs);
            }

            if ($name !== null) {
                $qb->andWhere(
                    $qb->expr()->orX(
                        $qb->expr()->like('LOWER(p.firstName)', ':name'),
                        $qb->expr()->like('LOWER(p.lastName)', ':name'),
                        $qb->expr()->like('LOWER(p.name)', ':name')
                    )
                )->setParameter('name', '%' . strtolower($name) . '%');
            }

            // ✅ Подсчёт общего количества записей
            $total = (clone $qb)
                ->select('COUNT(p.id)')
                ->getQuery()
                ->getSingleScalarResult();

            $players = $qb
                ->orderBy('p.id', 'ASC')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();

            return [
                'total' => (int) $total,
                'pages' => $limit > 0 ? (int) ceil($total / $limit) : 1,
                'data' => $players,
            ];
        });
    }



    /**
     * Получить список всех игроков для админки с пагинацией и фильтрацией по прицелу
     *
     * @param int $page Номер страницы
     * @param int $limit Количество записей на страницу
     * @param array $filters
     * @return Player[]
     */
    public function getAllPlayersForAdmin(int $page, int $limit, array $filters = []): array
    {
        $offset = ($page - 1) * $limit;
        $qb = $this->entityManager->getRepository(Player::class)->createQueryBuilder('p');

        if (!empty($filters['name'])) {
            $qb->andWhere('LOWER(p.name) LIKE :name')
                ->setParameter('name', '%' . strtolower($filters['name']) . '%');
        }

        if (!empty($filters['team'])) {
            $qb->join('p.team', 't')
                ->andWhere('LOWER(t.name) LIKE :team')
                ->setParameter('team', '%' . strtolower($filters['team']) . '%');
        }

        if (!empty($filters['country'])) {
            $qb->andWhere('p.country = :country')
                ->setParameter('country', $filters['country']);
        }

        if (isset($filters['hasCrosshair'])) {
            if ($filters['hasCrosshair']) {
                $qb->andWhere('p.crosshair IS NOT NULL');
            } else {
                $qb->andWhere('p.crosshair IS NULL');
            }
        }

        $total = (clone $qb)
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $players = $qb
            ->orderBy('p.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'players' => $players,
            'total' => (int) $total
        ];
    }


}
