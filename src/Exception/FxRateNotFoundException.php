<?php

namespace App\Exception;

use Exception;

class FxRateNotFoundException extends Exception
{
    public static function forCurrencyPair(
        string $fromCurrencyCode,
        string $toCurrencyCode,
        \DateTimeImmutable $asOfDate
    ): self {
        return new self(
            sprintf(
                'No FX rate found for conversion from %s to %s as of %s (within 7-day lookback window)',
                $fromCurrencyCode,
                $toCurrencyCode,
                $asOfDate->format('Y-m-d')
            )
        );
    }
}
