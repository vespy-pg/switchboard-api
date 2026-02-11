<?php

namespace App\Security\Voter;

use App\Entity\GiftListItemStatus;

class GiftListItemStatusVoter extends Voter
{
    public const LIST = 'GIFT_LIST_ITEM_STATUS_LIST';
    public const SHOW = 'GIFT_LIST_ITEM_STATUS_SHOW';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::LIST, self::SHOW], true);
    }

    protected function canList(GiftListItemStatus $subject): void
    {
        // Access control is handled by requireAttributeRole in base Voter
    }

    protected function canShow(GiftListItemStatus $subject): void
    {
        // Access control is handled by requireAttributeRole in base Voter
    }
}
