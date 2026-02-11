<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Service to encapsulate soft-delete filtering logic.
 * Provides a single source of truth for determining if an entity should be filtered out.
 */
class SoftDeleteFilterService
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    /**
     * Check if an entity should be included (not soft-deleted).
     *
     * @param object $entity The entity to check
     * @param array $context Optional context (e.g., serialization groups)
     * @return bool True if the entity should be included, false if it should be filtered out
     */
    public function shouldIncludeEntity(object $entity, array $context = []): bool
    {
        // Check if 'include_removed' group is present in context
        if ($this->shouldIncludeRemoved($context)) {
            return true;
        }

        // Check if the entity has a getRemovedAt method
        if (!method_exists($entity, 'getRemovedAt')) {
            // No removedAt field, always include
            return true;
        }

        // Include only if removedAt is null
        return $entity->getRemovedAt() === null;
    }

    /**
     * Check if the 'include_removed' group is present in the context.
     *
     * @param array $context The context to check
     * @return bool True if removed entities should be included
     */
    public function shouldIncludeRemoved(array $context): bool
    {
        $groups = $context['groups'] ?? [];
        return is_array($groups) && in_array('include_removed', $groups, true);
    }

    /**
     * Apply soft-delete filter to a QueryBuilder.
     *
     * @param QueryBuilder $queryBuilder The query builder to modify
     * @param string $resourceClass The entity class being queried
     * @return void
     */
    public function applyToQueryBuilder(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        // Check if the entity has a removedAt field
        if (!$this->hasRemovedAtField($resourceClass)) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere(sprintf('%s.removedAt IS NULL', $rootAlias));
    }

    /**
     * Check if an entity class has a removedAt field.
     *
     * @param string $resourceClass The entity class to check
     * @return bool True if the entity has a removedAt field
     */
    public function hasRemovedAtField(string $resourceClass): bool
    {
        $entityManager = $this->managerRegistry->getManagerForClass($resourceClass);
        if (!$entityManager) {
            return false;
        }

        $classMetadata = $entityManager->getClassMetadata($resourceClass);

        return $classMetadata->hasField('removedAt');
    }
}
