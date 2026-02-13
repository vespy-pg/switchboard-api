<?php

namespace App\Security\Voter;

use App\Entity\DeviceTypeCategory;

class DeviceTypeCategoryVoter extends Voter
{
    public const LIST = 'DEVICE_TYPE_CATEGORY_LIST';
    public const SHOW = 'DEVICE_TYPE_CATEGORY_SHOW';

    protected array $supportedAttributes = [self::SHOW, self::LIST];

    protected function canList(DeviceTypeCategory $subject): void
    {
    }

    protected function canShow(DeviceTypeCategory $subject): void
    {
    }
}
