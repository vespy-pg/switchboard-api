<?php

namespace App\Validator;

use App\Validator\Constraints\EntityUniqueConstraint;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class EntityUniqueConstraintValidator extends ConstraintValidator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof EntityUniqueConstraint) {
            throw new UnexpectedTypeException($constraint, EntityUniqueConstraint::class);
        }

        // Handle null values
        if ($value === null || $value === '') {
            return;
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        // Determine if this is a property-level or class-level constraint
        $isPropertyConstraint = $this->context->getPropertyPath() !== '';

        if ($isPropertyConstraint) {
            // Property-level validation: validate a single property value
            $this->validateProperty($value, $constraint, $propertyAccessor);
        } else {
            // Class-level validation: validate the entire object
            $this->validateClass($value, $constraint, $propertyAccessor);
        }
    }

    private function validateProperty(
        mixed $value,
        EntityUniqueConstraint $constraint,
        $propertyAccessor
    ): void {
        // For property-level validation, we need entityClass to be specified
        if ($constraint->entityClass === null) {
            throw new UnexpectedValueException(
                $constraint,
                'The "entityClass" parameter is required for property-level validation.'
            );
        }

        if (empty($constraint->fields)) {
            throw new UnexpectedValueException(
                $constraint,
                'The "field" or "fields" parameter is required for property-level validation.'
            );
        }

        $repository = $this->entityManager->getRepository($constraint->entityClass);

        // Build criteria - for property validation, we use the field name and the value being validated
        $criteria = [];
        foreach ($constraint->fields as $field) {
            $criteria[$field] = $value;
        }

        // Add conditions to criteria
        foreach ($constraint->conditions as $conditionField => $conditionValue) {
            $criteria[$conditionField] = $conditionValue;
        }

        // Check if entity with these criteria exists
        $existingEntity = $repository->findOneBy($criteria);

        if ($existingEntity !== null) {
            $fieldNames = implode(', ', $constraint->fields);
            $this->context
                ->buildViolation($constraint->message)
                ->setParameter('{{ fields }}', $fieldNames)
                ->addViolation();
        }
    }

    private function validateClass(
        mixed $value,
        EntityUniqueConstraint $constraint,
        $propertyAccessor
    ): void {
        // Determine the entity class to validate against
        $entityClass = $constraint->entityClass ?? get_class($value);
        $repository = $this->entityManager->getRepository($entityClass);

        // Build criteria from fields
        $criteria = [];
        foreach ($constraint->fields as $field) {
            try {
                $fieldValue = $propertyAccessor->getValue($value, $field);

                // Skip validation if field value is null or empty string
                if ($fieldValue === null || $fieldValue === '') {
                    return;
                }

                $criteria[$field] = $fieldValue;
            } catch (\Exception $e) {
                // If we can't access the field, skip validation
                return;
            }
        }

        // Add conditions to criteria
        foreach ($constraint->conditions as $conditionField => $conditionValue) {
            $criteria[$conditionField] = $conditionValue;
        }

        // Check if entity with these criteria exists
        $existingEntity = $repository->findOneBy($criteria);

        if ($existingEntity === null) {
            return;
        }

        // Get the ID field name (try common patterns)
        $idField = null;
        foreach (['id', 'code'] as $possibleIdField) {
            try {
                $propertyAccessor->getValue($value, $possibleIdField);
                $idField = $possibleIdField;
                break;
            } catch (\Exception $e) {
                continue;
            }
        }

        // If we found an ID field, check if we're updating the same entity
        if ($idField !== null) {
            try {
                $currentId = $propertyAccessor->getValue($value, $idField);
                $existingId = $propertyAccessor->getValue($existingEntity, $idField);

                // If IDs match, we're updating the same entity - no violation
                if ($currentId !== null && (string) $existingId === (string) $currentId) {
                    return;
                }
            } catch (\Exception $e) {
                // If we can't compare IDs, continue with validation
            }
        }

        // Build a user-friendly error message
        $fieldNames = implode(', ', $constraint->fields);
        $this->context
            ->buildViolation($constraint->message)
            ->setParameter('{{ fields }}', $fieldNames)
            ->addViolation();
    }
}
