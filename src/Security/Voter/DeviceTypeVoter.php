<?php

namespace App\Security\Voter;

use App\Entity\DeviceType;

class DeviceTypeVoter extends Voter
{
    public const LIST   = 'DEVICE_TYPE_LIST';
    public const CREATE = 'DEVICE_TYPE_CREATE';
    public const SHOW   = 'DEVICE_TYPE_SHOW';
    public const UPDATE = 'DEVICE_TYPE_UPDATE';
    protected array $supportedAttributes = [self::SHOW, self::LIST, self::CREATE, self::UPDATE];

    protected function canList(DeviceType $subject): void
    {
    }

    protected function canCreate(DeviceType $subject): void
    {
    }

    protected function canShow(DeviceType $subject): void
    {
    }

    protected function canUpdate(DeviceType $subject): void
    {
    }
}
