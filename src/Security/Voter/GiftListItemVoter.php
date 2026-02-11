<?php

namespace App\Security\Voter;

use App\Entity\GiftListItem;
use App\Exception\AccessDeniedException;

class GiftListItemVoter extends Voter
{
    public const LIST   = 'GIFT_LIST_ITEM_LIST';
    public const CREATE = 'GIFT_LIST_ITEM_CREATE';
    public const SHOW   = 'GIFT_LIST_ITEM_SHOW';
    public const UPDATE = 'GIFT_LIST_ITEM_UPDATE';
    public const DELETE = 'GIFT_LIST_ITEM_DELETE';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::LIST, self::CREATE, self::SHOW, self::UPDATE, self::DELETE], true);
    }

    protected function canList(GiftListItem $subject): void
    {
    }

    protected function canCreate(): void
    {
    }

    protected function canShow(GiftListItem $subject): void
    {
    }

    protected function canUpdate(GiftListItem $subject): void
    {
    }

    protected function canDelete(GiftListItem $subject): void
    {
    }
}
