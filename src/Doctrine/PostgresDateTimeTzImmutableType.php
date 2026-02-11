<?php

namespace App\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;

class PostgresDateTimeTzImmutableType extends DateTimeTzImmutableType
{
    public function convertToPHPValue($value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if ($value === null || $value instanceof \DateTimeImmutable) {
            return $value;
        }

        // Handle PostgreSQL format with microseconds: 2026-01-06 10:16:18.877+00
        $formats = [
            'Y-m-d H:i:s.uP',  // With microseconds and timezone (e.g., 2026-01-06 10:16:18.877+00:00)
            'Y-m-d H:i:s.uO',  // With microseconds and timezone (e.g., 2026-01-06 10:16:18.877+0000)
            'Y-m-d H:i:sP',    // Without microseconds (e.g., 2026-01-06 10:16:18+00:00)
            'Y-m-d H:i:sO',    // Without microseconds (e.g., 2026-01-06 10:16:18+0000)
        ];

        foreach ($formats as $format) {
            $val = \DateTimeImmutable::createFromFormat($format, $value);
            if ($val !== false) {
                return $val;
            }
        }

        // Fallback to parent implementation
        return parent::convertToPHPValue($value, $platform);
    }
}
