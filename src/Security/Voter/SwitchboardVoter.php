<?php

namespace App\Security\Voter;

use App\Entity\Switchboard;

class SwitchboardVoter extends Voter
{
    public const LIST   = 'SWITCHBOARD_LIST';
    public const CREATE = 'SWITCHBOARD_CREATE';
    public const SHOW   = 'SWITCHBOARD_SHOW';
    public const UPDATE = 'SWITCHBOARD_UPDATE';
    protected array $supportedAttributes = [self::SHOW, self::LIST, self::CREATE, self::UPDATE];

    protected function canList(Switchboard $subject): void
    {
    }

    protected function canCreate(Switchboard $subject): void
    {
    }

    protected function canShow(Switchboard $subject): void
    {
    }

    protected function canUpdate(Switchboard $subject): void
    {
    }
}
