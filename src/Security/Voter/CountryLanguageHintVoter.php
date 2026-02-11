<?php

namespace App\Security\Voter;

use App\Entity\CountryLanguageHint;

class CountryLanguageHintVoter extends Voter
{
    public const LIST = 'COUNTRY_LANGUAGE_HINT_LIST';
    public const SHOW = 'COUNTRY_LANGUAGE_HINT_SHOW';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::LIST, self::SHOW], true);
    }

    protected function canList(CountryLanguageHint $subject): void
    {
        // Access control is handled by requireAttributeRole in base Voter
    }

    protected function canShow(CountryLanguageHint $subject): void
    {
        // Access control is handled by requireAttributeRole in base Voter
    }
}
