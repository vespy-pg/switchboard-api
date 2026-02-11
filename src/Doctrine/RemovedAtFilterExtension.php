<?php

declare(strict_types=1);

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Service\SoftDeleteFilterService;
use Doctrine\ORM\QueryBuilder;

/**
 * Extension to filter out soft-deleted entities globally.
 * Only returns entities where removedAt is null, unless 'include_removed' group is present.
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
}
