<?php

namespace App\Security\Voter;

use App\Entity\Event;
use App\Exception\AccessDeniedException;

class EventVoter extends Voter
{
    public const LIST   = 'EVENT_LIST';
    public const CREATE = 'EVENT_CREATE';
    public const SHOW   = 'EVENT_SHOW';
    public const UPDATE = 'EVENT_UPDATE';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::LIST, self::CREATE, self::SHOW, self::UPDATE], true);
    }

    protected function canList(Event $subject): void
    {
    }

    protected function canCreate(): void
    {
    }

    protected function canShow(Event $subject): void
    {
    }

    protected function canUpdate(Event $subject): void
    {
    }
}
