<?php

namespace App\Security\Voter;

use App\Entity\GiftListType;

class GiftListTypeVoter extends Voter
{
    public const LIST = 'GIFT_LIST_TYPE_LIST';
    public const SHOW = 'GIFT_LIST_TYPE_SHOW';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::LIST, self::SHOW], true);
    }

    protected function canList(GiftListType $subject): void
    {
        // Access control is handled by requireAttributeRole in base Voter
    }

    protected function canShow(GiftListType $subject): void
    {
        // Access control is handled by requireAttributeRole in base Voter
    }
}
