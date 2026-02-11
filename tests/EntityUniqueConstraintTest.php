<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use App\Validator\Constraints\EntityUniqueConstraint;

class EntityUniqueConstraintTest extends TestCase
{
    public function testEntityUniqueConstraintCanBeInstantiated(): void
    {
        // Test class-level constraint
        $constraint = new EntityUniqueConstraint(
            fields: ['email'],
            conditions: ['removedAt' => null],
            message: 'This email is already in use.'
        );

        $this->assertInstanceOf(EntityUniqueConstraint::class, $constraint);
        $this->assertEquals(['email'], $constraint->fields);
        $this->assertEquals(['removedAt' => null], $constraint->conditions);
        $this->assertEquals('This email is already in use.', $constraint->message);
    }

    public function testEntityUniqueConstraintWithEntityClass(): void
    {
        // Test property-level constraint with entityClass
        $constraint = new EntityUniqueConstraint(
            entityClass: 'App\Entity\User',
            field: 'email',
            message: 'This email is already registered.'
        );

        $this->assertInstanceOf(EntityUniqueConstraint::class, $constraint);
        $this->assertEquals('App\Entity\User', $constraint->entityClass);
        $this->assertEquals(['email'], $constraint->fields);
        $this->assertEquals('This email is already registered.', $constraint->message);
    }

    public function testEntityUniqueConstraintWithMultipleFields(): void
    {
        // Test with multiple fields
        $constraint = new EntityUniqueConstraint(
            field: ['firstName', 'lastName'],
            entityClass: 'App\Entity\Person'
        );

        $this->assertInstanceOf(EntityUniqueConstraint::class, $constraint);
        $this->assertEquals(['firstName', 'lastName'], $constraint->fields);
    }

    public function testEntityUniqueConstraintTargets(): void
    {
        $constraint = new EntityUniqueConstraint();
        $targets = $constraint->getTargets();

        $this->assertIsArray($targets);
        $this->assertContains(EntityUniqueConstraint::CLASS_CONSTRAINT, $targets);
        $this->assertContains(EntityUniqueConstraint::PROPERTY_CONSTRAINT, $targets);
    }
}
