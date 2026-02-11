<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Service\SoftDeleteFilterService;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class OneToManyCountsNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED_KEY = 'ONE_TO_MANY_COUNTS_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly SoftDeleteFilterService $softDeleteFilterService,
        private readonly string $triggerGroup = 'with_counts',
        private readonly string $suffix = 'Count',
    ) {
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (!is_object($data)) {
            return false;
        }

        // Avoid circular reference - check if this specific object was already processed
        $objectHash = spl_object_hash($data);
        if (isset($context[self::ALREADY_CALLED_KEY][$objectHash])) {
            return false;
        }

        $groups = $context['groups'] ?? [];
        if (!is_array($groups) || !in_array($this->triggerGroup, $groups, true)) {
            return false;
        }

        $entityManager = $this->getEntityManagerForObject($data);
        if (!$entityManager instanceof EntityManagerInterface) {
            return false;
        }

        // Avoid touching non-entities.
        return !$entityManager->getMetadataFactory()->isTransient(get_class($data));
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|null
    {
        // Mark this specific object as being processed
        $objectHash = spl_object_hash($data);
        $context[self::ALREADY_CALLED_KEY][$objectHash] = true;

        $normalized = $this->normalizer->normalize($data, $format, $context);

        // If normalized result is not an array, return as-is
        if (!is_array($normalized)) {
            return $normalized;
        }

        $entityManager = $this->getEntityManagerForObject($data);
        if (!$entityManager instanceof EntityManagerInterface) {
            return $normalized;
        }

        $classMetadata = $entityManager->getClassMetadata($data::class);

        foreach ($classMetadata->associationMappings as $associationName => $associationMapping) {
            if (($associationMapping['type'] ?? null) !== \Doctrine\ORM\Mapping\ClassMetadata::ONE_TO_MANY) {
                continue;
            }

            $countFieldName = $associationName . $this->suffix;

            // Don't overwrite if something already produced it
            if (array_key_exists($countFieldName, $normalized)) {
                continue;
            }

            $countValue = $this->countAssociation($data, $associationName, $context);

            $normalized[$countFieldName] = $countValue;
        }

        // Process nested objects/arrays recursively
        $normalized = $this->processNestedObjects($normalized, $format, $context);

        return $normalized;
    }

    private function processNestedObjects(array $data, ?string $format, array $context): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Check if it's an associative array (object) or indexed array (collection)
                if ($this->isAssociativeArray($value)) {
                    // It's a normalized object, process it recursively
                    $data[$key] = $this->processNestedObjects($value, $format, $context);
                } else {
                    // It's a collection, process each item
                    foreach ($value as $itemKey => $item) {
                        if (is_array($item)) {
                            $data[$key][$itemKey] = $this->processNestedObjects($item, $format, $context);
                        }
                    }
                }
            }
        }

        return $data;
    }

    private function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return true;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function getEntityManagerForObject(object $object): ?EntityManagerInterface
    {
        $objectManager = $this->managerRegistry->getManagerForClass($object::class);

        if (!$objectManager instanceof EntityManagerInterface) {
            return null;
        }

        return $objectManager;
    }

    private function countAssociation(object $object, string $associationName, array $context): int
    {
        $getterName = 'get' . ucfirst($associationName);

        if (!method_exists($object, $getterName)) {
            return 0;
        }

        $value = $object->$getterName();

        if ($value instanceof Collection) {
            // Use the shared service to filter out soft-deleted items (respects include_removed group)
            return $value->filter(fn($item) => $this->softDeleteFilterService->shouldIncludeEntity($item, $context))->count();
        }

        if (is_array($value)) {
            // Use the shared service to filter out soft-deleted items from array (respects include_removed group)
            $filtered = array_filter($value, fn($item) => is_object($item) && $this->softDeleteFilterService->shouldIncludeEntity($item, $context));
            return count($filtered);
        }

        return 0;
    }

    public function getSupportedTypes(?string $format): array
    {
        // This normalizer supports all object types, but only when the context matches
        // The '*' => false tells Symfony to always call supportsNormalization()
        return ['*' => false];
    }
}
