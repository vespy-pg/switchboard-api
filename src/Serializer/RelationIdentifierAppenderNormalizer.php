<?php

namespace App\Serializer;

use ApiPlatform\Metadata\IriConverterInterface;
use ArrayObject;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class RelationIdentifierAppenderNormalizer implements NormalizerInterface, SerializerAwareInterface
{
    private const CONTEXT_ALREADY_CALLED = 'app_relation_identifier_appender_normalizer_called';

    public function __construct(
        private readonly NormalizerInterface $decoratedNormalizer,
        private readonly ManagerRegistry $managerRegistry,
        private readonly IriConverterInterface $iriConverter
    ) {
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        if ($this->decoratedNormalizer instanceof SerializerAwareInterface) {
            $this->decoratedNormalizer->setSerializer($serializer);
        }
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (!is_object($data)) {
            return false;
        }

        if (!empty($context[self::CONTEXT_ALREADY_CALLED])) {
            return false;
        }

        $entityManager = $this->managerRegistry->getManagerForClass($data::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            return false;
        }

        return !$entityManager->getMetadataFactory()->isTransient($data::class);
    }

    public function getSupportedTypes(?string $format): array
    {
        // We do runtime checks in supportsNormalization()
        return ['object' => false];
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|ArrayObject|bool|float|int|string|null
    {
        $context[self::CONTEXT_ALREADY_CALLED] = true;

        $normalizedData = $this->decoratedNormalizer->normalize($data, $format, $context);
        if (!is_array($normalizedData)) {
            return $normalizedData;
        }

        $entityManager = $this->managerRegistry->getManagerForClass($data::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            return $normalizedData;
        }

        // Add @id IRI to the root entity if not already present
        if (!isset($normalizedData['@id'])) {
            try {
                $iri = $this->iriConverter->getIriFromResource($data);
                // Prepend @id as the first property
                $normalizedData = ['@id' => $iri] + $normalizedData;
            } catch (\Exception $e) {
                // If IRI generation fails, continue without it
            }
        }

        $classMetadata = $entityManager->getClassMetadata($data::class);

        foreach ($classMetadata->associationMappings as $associationName => $associationMapping) {
            $associationValue = $this->readAssociationValue($data, $associationName);

            // to-one: <relationName>Identifier
            if (is_object($associationValue) && !($associationValue instanceof Collection)) {
                $normalizedData[$associationName . 'Identifier'] = $this->getEntityIdentifierValue($entityManager, $associationValue);

                // If the relation is serialized as a nested object, add @id IRI to it as the first property
                if (isset($normalizedData[$associationName]) && is_array($normalizedData[$associationName])) {
                    try {
                        $iri = $this->iriConverter->getIriFromResource($associationValue);
                        // Prepend @id as the first property
                        $normalizedData[$associationName] = ['@id' => $iri] + $normalizedData[$associationName];
                    } catch (\Exception $e) {
                        // If IRI generation fails, continue without it
                    }
                }
                continue;
            }

            if ($associationValue === null) {
                $normalizedData[$associationName . 'Identifier'] = null;
                continue;
            }

            // to-many: <relationName>Identifiers (only if initialized)
            if ($associationValue instanceof Collection) {
                if (method_exists($associationValue, 'isInitialized') && !$associationValue->isInitialized()) {
                    $normalizedData[$associationName . 'Identifiers'] = null;
                    continue;
                }

                $relatedIdentifiers = [];
                foreach ($associationValue as $relatedObject) {
                    if (!is_object($relatedObject)) {
                        continue;
                    }

                    $relatedIdentifiers[] = $this->getEntityIdentifierValue($entityManager, $relatedObject);
                }

                $normalizedData[$associationName . 'Identifiers'] = $relatedIdentifiers;

                // If the relation is serialized as a nested array of objects, add @id IRI to each as the first property
                if (isset($normalizedData[$associationName]) && is_array($normalizedData[$associationName])) {
                    foreach ($normalizedData[$associationName] as $index => $item) {
                        if (is_array($item) && isset($associationValue[$index])) {
                            try {
                                $iri = $this->iriConverter->getIriFromResource($associationValue[$index]);
                                // Prepend @id as the first property
                                $normalizedData[$associationName][$index] = ['@id' => $iri] + $item;
                            } catch (\Exception $e) {
                                // If IRI generation fails, continue without it
                            }
                        }
                    }
                }
            }
        }

        return $normalizedData;
    }

    private function readAssociationValue(object $object, string $associationName): mixed
    {
        $getterName = 'get' . ucfirst($associationName);

        if (method_exists($object, $getterName)) {
            // For to-one associations, this typically returns a proxy without initializing it
            return $object->$getterName();
        }

        if (property_exists($object, $associationName)) {
            return $object->$associationName;
        }

        return null;
    }

    private function getEntityIdentifierValue(EntityManagerInterface $entityManager, object $entity): mixed
    {
        // This does not need the entity initialized; it works with proxies.
        $unitOfWork = $entityManager->getUnitOfWork();
        $identifierValues = $unitOfWork->getEntityIdentifier($entity);

        if (count($identifierValues) === 0) {
            return null;
        }

        // You said no composite PKs. Still, keep safe behavior:
        if (count($identifierValues) === 1) {
            return array_values($identifierValues)[0];
        }

        // Composite PK fallback: return associative array (or join into a string if you prefer)
        return $identifierValues;
    }
}
