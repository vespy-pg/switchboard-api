<?php

namespace App\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class BitBooleanType extends Type
{
    public const BIT_BOOLEAN = 'bit_boolean';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'BIT(1)';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?bool
    {
        if ($value === null) {
            return null;
        }

        return $value === '1'; // PostgreSQL represents true as '1' in BIT(1)
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value ? '1' : '0'; // Store as '1' or '0' in the database
    }

    public function getName(): string
    {
        return self::BIT_BOOLEAN;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
