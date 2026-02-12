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
    protected array $supportedAttributes = [self::SHOW, self::LIST, self::CREATE, self::UPDATE];

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
