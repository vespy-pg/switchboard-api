<?php

namespace App\Security\Voter;

use App\Entity\GiftList;
use App\Exception\AccessDeniedException;

class GiftListVoter extends Voter
{
    public const LIST   = 'GIFT_LIST_LIST';
    public const CREATE = 'GIFT_LIST_CREATE';
    public const SHOW   = 'GIFT_LIST_SHOW';
    public const UPDATE = 'GIFT_LIST_UPDATE';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::LIST, self::CREATE, self::SHOW, self::UPDATE], true);
    }

    protected function canList(GiftList $subject): void
    {
    }

    protected function canCreate(): void
    {
    }

    protected function canShow(GiftList $subject): void
    {
    }

    protected function canUpdate(GiftList $subject): void
    {
    }
}
