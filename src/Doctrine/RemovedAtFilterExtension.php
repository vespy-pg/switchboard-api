<?php

declare(strict_types=1);

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Project;
use App\Service\SoftDeleteFilterService;
use Doctrine\ORM\QueryBuilder;

/**
 * Extension to filter out soft-deleted entities globally.
 * Only returns entities where removedAt is null, unless 'include_removed' group is present.
 * For projects, archived entities are excluded unless 'include_archived' group is present.
 */
class RemovedAtFilterExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private readonly SoftDeleteFilterService $softDeleteFilterService,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = []
    ): void {
        $this->addWhere($queryBuilder, $resourceClass, $context);
        $this->addProjectArchivedWhere($queryBuilder, $resourceClass, $operation, $context);
        $this->applyDefaultProjectOrdering($queryBuilder, $resourceClass);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        Operation $operation = null,
        array $context = []
    ): void {
        $this->addWhere($queryBuilder, $resourceClass, $context);
        $this->addProjectArchivedWhere($queryBuilder, $resourceClass, $operation, $context);
    }

    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass, array $context): void
    {
        // Check if 'include_removed' group is present in context
        if ($this->softDeleteFilterService->shouldIncludeRemoved($context)) {
            return;
        }

        // Use the shared service to apply the filter
        $this->softDeleteFilterService->applyToQueryBuilder($queryBuilder, $resourceClass);
    }

    private function addProjectArchivedWhere(QueryBuilder $queryBuilder, string $resourceClass, ?Operation $operation, array $context): void
    {
        if ($operation?->getUriTemplate() === '/projects/{id}/unarchive') {
            return;
        }

        if ($resourceClass !== Project::class || $this->softDeleteFilterService->shouldIncludeArchived($context)) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere(sprintf('%s.archivedAt IS NULL', $rootAlias));
    }

    private function applyDefaultProjectOrdering(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if ($resourceClass !== Project::class) {
            return;
        }

        if ($queryBuilder->getDQLPart('orderBy') !== []) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        $queryBuilder
            ->addSelect(sprintf('CASE WHEN %s.archivedAt IS NULL THEN 0 ELSE 1 END AS HIDDEN archivedSort', $rootAlias))
            ->addOrderBy('archivedSort', 'ASC')
            ->addOrderBy(sprintf('%s.createdAt', $rootAlias), 'DESC');
    }
}
