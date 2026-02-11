<?php

namespace App\Security\Voter;

use App\Entity\Currency;

class CurrencyVoter extends Voter
{
    public const LIST = 'CURRENCY_LIST';
    public const SHOW = 'CURRENCY_SHOW';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::LIST, self::SHOW], true);
    }

    protected function canList(Currency $subject): void
    {
        // Access control is handled by requireAttributeRole in base Voter
    }

    protected function canShow(Currency $subject): void
    {
        // Access control is handled by requireAttributeRole in base Voter
    }
}
