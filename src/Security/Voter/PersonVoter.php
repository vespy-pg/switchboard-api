<?php

namespace App\Security\Voter;

use App\Entity\Person;
use App\Exception\AccessDeniedException;

class PersonVoter extends Voter
{
    public const LIST   = 'PERSON_LIST';
    public const CREATE = 'PERSON_CREATE';
    public const SHOW   = 'PERSON_SHOW';
    public const UPDATE = 'PERSON_UPDATE';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::LIST, self::CREATE, self::SHOW, self::UPDATE], true);
    }

    protected function canList(Person $subject): void
    {
    }

    protected function canCreate(): void
    {
    }

    protected function canShow(Person $subject): void
    {
    }

    protected function canUpdate(Person $subject): void
    {
    }
}
