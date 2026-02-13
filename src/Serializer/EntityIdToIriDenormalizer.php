<?php

namespace App\Serializer;

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Converts entity IDs to IRIs during denormalization.
 * This allows API endpoints to accept both IRI format ("/api/calendars/123")
 * and simple ID format (123) for entity relations.
 */
class EntityIdToIriDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'ENTITY_ID_TO_IRI_DENORMALIZER_ALREADY_CALLED';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private IriConverterInterface $iriConverter
    ) {
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        // Prevent infinite recursion
        $context[self::ALREADY_CALLED] = true;

        // Transform entity IDs to IRIs in the data array
        if (is_array($data)) {
            $data = $this->transformData($data, $type);
        }

        // Call the next denormalizer in the chain
        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        // Avoid infinite loop
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        // Only process arrays (request data)
        return is_array($data);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => false, // We support all types but with low priority
        ];
    }

    /**
     * Transform entity IDs to IRIs in the data array
     */
    private function transformData(array $data, string $entityClass): array
    {
        if (!class_exists($entityClass)) {
            return $data;
        }

        // Skip non-entity classes (DTOs, etc.)
        try {
            $metadata = $this->entityManager->getClassMetadata($entityClass);
        } catch (\Exception $e) {
            // Not an entity, skip transformation
            return $data;
        }

        foreach ($data as $property => $value) {
            // Skip null values
            if ($value === null) {
                continue;
            }

            // Check if this property is an association
            if (!$metadata->hasAssociation($property)) {
                continue;
            }

            $targetEntity = $metadata->getAssociationTargetClass($property);

            // Handle single entity relation
            if ($this->isSingleAssociation($metadata, $property)) {
                $data[$property] = $this->convertToIri($value, $targetEntity);
            } elseif (is_array($value)) {
                // Handle collection of entities
                $data[$property] = array_map(
                    fn($item) => $this->convertToIri($item, $targetEntity),
                    $value
                );
            }
        }

        return $data;
    }

    /**
     * Convert a value to IRI if it's a numeric ID or string-based PK (e.g., _id or _code)
     */
    private function convertToIri(mixed $value, string $entityClass): mixed
    {
        // If it's already an IRI (string starting with /), return as is
        if (is_string($value) && str_starts_with($value, '/')) {
            return $value;
        }

        // If it's an array (nested entity data), return as is
        if (is_array($value)) {
            return $value;
        }

        // If it's numeric (ID) or a non-empty string (could be a code/string PK), convert to IRI
        if (is_numeric($value) || (is_string($value) && ctype_digit($value)) || (is_string($value) && !empty($value))) {
            try {
                // Get the entity reference (this doesn't hit the database)
                $entity = $this->entityManager->getReference($entityClass, $value);

                // Try to convert to IRI - this may throw an exception if the IRI cannot be generated
                $iri = $this->iriConverter->getIriFromResource($entity);

                // Only return the IRI if it was successfully generated and is valid
                if ($iri && is_string($iri) && str_starts_with($iri, '/')) {
                    return $iri;
                }

                // If IRI is not valid, return original value
                return $value;
            } catch (\Throwable $e) {
                // If conversion fails for any reason, return original value
                // The validator will catch invalid references later
                return $value;
            }
        }

        return $value;
    }

    /**
     * Check if association is a single entity (not a collection)
     */
    private function isSingleAssociation($metadata, string $property): bool
    {
        return $metadata->isSingleValuedAssociation($property);
    }
}
