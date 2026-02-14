<?php

namespace App\Serializer;

use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Maps flat relation alias fields (e.g. projectId, deviceTypeCode) to relation properties
 * before regular relation denormalization is executed.
 */
class RelationAliasDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'RELATION_ALIAS_DENORMALIZER_ALREADY_CALLED';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $context[self::ALREADY_CALLED] = true;

        if (is_array($data)) {
            $data = $this->mapAliasesToRelations($data, $type);
        }

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return is_array($data);
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    private function mapAliasesToRelations(array $data, string $entityClass): array
    {
        if (!class_exists($entityClass)) {
            return $data;
        }

        try {
            $metadata = $this->entityManager->getClassMetadata($entityClass);
        } catch (\Throwable $e) {
            return $data;
        }

        foreach ($metadata->associationMappings as $associationName => $mapping) {
            $targetEntityClass = $mapping['targetEntity'] ?? null;
            if (!is_string($targetEntityClass)) {
                continue;
            }

            try {
                $targetMetadata = $this->entityManager->getClassMetadata($targetEntityClass);
            } catch (\Throwable $e) {
                continue;
            }

            $identifierFields = $targetMetadata->getIdentifierFieldNames();
            if (count($identifierFields) !== 1) {
                continue;
            }

            $identifierField = $identifierFields[0];
            $targetShortName = lcfirst((new ReflectionClass($targetEntityClass))->getShortName());

            $aliases = array_unique([
                $associationName . ucfirst($identifierField),
                $targetShortName . ucfirst($identifierField),
            ]);

            foreach ($aliases as $alias) {
                if (!array_key_exists($alias, $data)) {
                    continue;
                }

                if (array_key_exists($associationName, $data)) {
                    // Keep backward compatibility: explicit relation field takes precedence.
                    unset($data[$alias]);

                    continue;
                }

                $data[$associationName] = $data[$alias];
                unset($data[$alias]);
            }
        }

        return $data;
    }
}
