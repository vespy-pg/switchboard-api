<?php

namespace App\Security\Voter;

use App\Entity\GiftListItemComment;
use App\Exception\AccessDeniedException;

class GiftListItemCommentVoter extends Voter
{
    public const LIST = 'GIFT_LIST_ITEM_COMMENT_LIST';
    public const SHOW = 'GIFT_LIST_ITEM_COMMENT_SHOW';
    public const CREATE = 'GIFT_LIST_ITEM_COMMENT_CREATE';
    public const DELETE = 'GIFT_LIST_ITEM_COMMENT_DELETE';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::LIST, self::SHOW, self::CREATE, self::DELETE], true);
    }

    protected function canList(GiftListItemComment $subject): void
    {
        // Access control is handled by requireAttributeRole in base Voter
    }

    protected function canShow(GiftListItemComment $subject): void
    {
        // Access control is handled by requireAttributeRole in base Voter
    }

    protected function canCreate(): void
    {
    }

    protected function canDelete(GiftListItemComment $subject): void
    {
        if (!$subject->getCreatedByUser() === $this->user || $this->user->hasRole('ROLE_ADMIN')) {
            throw new AccessDeniedException('Only owner can delete comments.');
        }
    }
}
