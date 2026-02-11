<?php

namespace App\Security\Voter;

use App\Entity\Country;

class CountryVoter extends Voter
{
    public const LIST = 'COUNTRY_LIST';
    public const SHOW = 'COUNTRY_SHOW';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::LIST, self::SHOW], true);
    }

    protected function canList(Country $subject): void
    {
        // Access control is handled by requireAttributeRole in base Voter
    }

    protected function canShow(Country $subject): void
    {
        // Access control is handled by requireAttributeRole in base Voter
    }
}
