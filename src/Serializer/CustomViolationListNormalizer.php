<?php

namespace App\Serializer;

use App\Validator\Constraints\EntityUniqueConstraint;
use ArrayObject;
use ReflectionClass;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class CustomViolationListNormalizer implements NormalizerInterface
{
    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return $data instanceof ConstraintViolationListInterface;
    }

    public function normalize(
        $data,
        string $format = null,
        array $context = []
    ): float|int|bool|ArrayObject|array|string|null {
        $violations = [];
        foreach ($data as $violation) {
            $violations[] = [
                'propertyPath' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
                'constraint' => $this->getConstraintName($violation->getConstraint()),
            ];
        }
        return ['violations' => $violations];
    }

    private function getConstraintName($constraint)
    {
        $reflectionClass = new ReflectionClass($constraint);
        switch ($reflectionClass->getName()) {
            case EntityUniqueConstraint::class:
            case UniqueEntity::class:
                return 'UNIQUE';
            default:
                return $this->convertConstraintName($reflectionClass->getShortName());
        }
    }

    private function convertConstraintName(string $name): string
    {
        return strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ConstraintViolationListInterface::class => true];
    }
}
