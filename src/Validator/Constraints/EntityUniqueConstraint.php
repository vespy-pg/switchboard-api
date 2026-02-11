<?php

namespace App\Validator\Constraints;

use App\Validator\EntityUniqueConstraintValidator;
use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class EntityUniqueConstraint extends Constraint
{
    public string $message = 'This combination of values is already in use.';

    /**
     * @var array<string>
     */
    public array $fields = [];

    /**
     * @param string|null $entityClass Entity class to validate against (required for DTO validation)
     * @param string|array<string>|null $field Field(s) to check for uniqueness (for property-level usage)
     * @param array<string> $fields Fields to check for uniqueness (for class-level usage)
     * @param array<string, mixed> $conditions Additional conditions (e.g., ['removedAt' => null])
     * @param string|null $message Custom error message
     * @param array|null $groups Validation groups
     * @param mixed $payload Additional payload
     */
    public function __construct(
        public readonly ?string $entityClass = null,
        string|array|null $field = null,
        array $fields = [],
        public readonly array $conditions = [],
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        // Convert single field to array for internal consistency
        if ($field !== null) {
            $fields = is_array($field) ? $field : [$field];
        }

        parent::__construct([], $groups, $payload);

        if ($message !== null) {
            $this->message = $message;
        }

        $this->fields = $fields;
    }

    public function getTargets(): string|array
    {
        return [self::CLASS_CONSTRAINT, self::PROPERTY_CONSTRAINT];
    }

    public function validatedBy(): string
    {
        return EntityUniqueConstraintValidator::class;
    }
}
