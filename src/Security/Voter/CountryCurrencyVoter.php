<?php

namespace App\Security\Voter;

use App\Entity\CountryCurrency;

class CountryCurrencyVoter extends Voter
{
    public const LIST = 'COUNTRY_CURRENCY_LIST';
    public const SHOW = 'COUNTRY_CURRENCY_SHOW';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::LIST, self::SHOW], true);
    }

    protected function canList(): void
    {
        // Access control is handled by requireAttributeRole in base Voter
    }

    protected function canShow(CountryCurrency $subject): void
    {
        // Access control is handled by requireAttributeRole in base Voter
    }
}
