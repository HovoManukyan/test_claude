<?php

namespace App\Doctrine;

use App\DTO\PaginationMetadata;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;
use Doctrine\ORM\Tools\Pagination\CountWalker;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
#[Exclude]
class Paginator
{
    /**
     * Use constants to define configuration options that rarely change instead
     * of specifying them under parameters section in config/services.yaml file.
     *
     * See https://symfony.com/doc/current/best_practices.html#use-constants-to-define-options-that-rarely-change
     */
    public const FIRST_PAGE = 1;
    public const PAGE_SIZE = 10;

    private int $currentPage;
    private int $numResults;
    private array $results;
    private int $hydrationMode = AbstractQuery::HYDRATE_OBJECT;

    public function __construct(
        private DoctrineQueryBuilder $queryBuilder,
        private int $pageSize = self::PAGE_SIZE
    ) {
    }

    public function setHydrationMode(int $mode): self
    {
        $this->hydrationMode = $mode;

        return $this;
    }

    public function paginate(int $page = 1, ?bool $useOutputWalkers = null): self
    {
        $this->currentPage = (int)max(1, $page);
        $firstResult = ($this->currentPage - 1) * $this->pageSize;

        $query = $this->queryBuilder
            ->setFirstResult($firstResult)
            ->setMaxResults($this->pageSize)
            ->getQuery();

        if (0 === \count($this->queryBuilder->getDQLPart('join'))) {
            $query->setHint(CountWalker::HINT_DISTINCT, false);
        }

        $paginator = new DoctrinePaginator($query, true);

        if (null === $useOutputWalkers) {
            $havingCount = $this->queryBuilder->getDQLPart('having')?->count() ?? 0;
            $groupByCount = count($this->queryBuilder->getDQLPart('groupBy'));

            $useOutputWalkers = $havingCount > 0 || $groupByCount > 0;
        }

        $paginator->setUseOutputWalkers($useOutputWalkers);

        $this->numResults = $paginator->count();
        $this->results = $paginator->getQuery()->getResult($this->hydrationMode);

        return $this;
    }

    public function getMetadata(): PaginationMetadata
    {
        return new PaginationMetadata(
            $this->currentPage,
            $this->pageSize,
            $this->getLastPage(),
            $this->numResults
        );
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getLastPage(): int
    {
        return (int)ceil($this->numResults / $this->pageSize);
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    public function getPreviousPage(): int
    {
        return max(1, $this->currentPage - 1);
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->getLastPage();
    }

    public function getNextPage(): int
    {
        return min($this->getLastPage(), $this->currentPage + 1);
    }

    public function hasToPaginate(): bool
    {
        return $this->numResults > $this->pageSize;
    }

    public function getNumResults(): int
    {
        return $this->numResults;
    }

    /** @return mixed[] */
    public function getResults(): array
    {
        return $this->results;
    }
}
