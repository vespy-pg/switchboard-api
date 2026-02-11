<?php

namespace App\Security\Voter;

use App\Entity\GiftListItemReservation;

class GiftListItemReservationVoter extends Voter
{
    public const LIST = 'GIFT_LIST_ITEM_RESERVATION_LIST';
    public const SHOW = 'GIFT_LIST_ITEM_RESERVATION_SHOW';
    public const CREATE = 'GIFT_LIST_ITEM_RESERVATION_CREATE';
    public const DELETE = 'GIFT_LIST_ITEM_RESERVATION_DELETE';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::LIST, self::SHOW, self::CREATE, self::DELETE], true);
    }

    protected function canList(GiftListItemReservation $subject): void
    {
        // Access control is handled by requireAttributeRole in base Voter
    }

    protected function canShow(GiftListItemReservation $subject): void
    {
        // Access control is handled by requireAttributeRole in base Voter
    }

    protected function canCreate()
    {
    }

    protected function canDelete()
    {
    }
}
