<?php

namespace App\Security\Voter;

use App\Entity\Language;

class LanguageVoter extends Voter
{
    public const LIST = 'LANGUAGE_LIST';
    public const SHOW = 'LANGUAGE_SHOW';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::LIST, self::SHOW], true);
    }

    protected function canList(Language $subject): void
    {
        // Access control is handled by requireAttributeRole in base Voter
    }

    protected function canShow(Language $subject): void
    {
        // Access control is handled by requireAttributeRole in base Voter
    }
}
