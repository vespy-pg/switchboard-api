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
    private const GROUP_INCLUDE_REMOVED = 'include_removed';
    private const GROUP_INCLUDE_ARCHIVED = 'include_archived';

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
        return $this->hasGroup($context, self::GROUP_INCLUDE_REMOVED);
    }

    /**
     * Check if the 'include_archived' group is present in the context.
     *
     * @param array $context The context to check
     * @return bool True if archived entities should be included
     */
    public function shouldIncludeArchived(array $context): bool
    {
        return $this->hasGroup($context, self::GROUP_INCLUDE_ARCHIVED);
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

    /**
     * Determine if a serialization/filter group is present in API Platform context.
     *
     * @param array $context Operation context
     * @param string $group Group name to check
     * @return bool
     */
    private function hasGroup(array $context, string $group): bool
    {
        $groups = $context['groups'] ?? [];
        if (is_string($groups)) {
            $groups = [$groups];
        }

        if (is_array($groups) && in_array($group, $groups, true)) {
            return true;
        }

        $filterGroups = $context['filters']['groups'] ?? [];
        if (is_string($filterGroups)) {
            $filterGroups = [$filterGroups];
        }

        return is_array($filterGroups) && in_array($group, $filterGroups, true);
    }
}
