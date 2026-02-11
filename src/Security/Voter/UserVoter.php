<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Exception\AccessDeniedException;

class UserVoter extends Voter
{
    public const LIST   = 'USER_LIST';
    public const CREATE = 'USER_CREATE';
    public const SHOW   = 'USER_SHOW';
    public const UPDATE = 'USER_UPDATE';
    public const BASIC_USER = 'ROLE_BASIC_USER';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::LIST, self::CREATE, self::SHOW, self::UPDATE], true);
    }

    protected function canList(User $subject): void
    {
    }

    protected function canCreate(User $subject): void
    {
    }

    protected function canShow(User $subject): void
    {
    }

    protected function canUpdate(User $subject): void
    {
    }
}
