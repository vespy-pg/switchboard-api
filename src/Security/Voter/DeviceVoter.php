<?php

namespace App\Security\Voter;

use App\Entity\Device;
use App\Exception\AccessDeniedException;

class DeviceVoter extends Voter
{
    public const LIST   = 'DEVICE_LIST';
    public const CREATE = 'DEVICE_CREATE';
    public const SHOW   = 'DEVICE_SHOW';
    public const UPDATE = 'DEVICE_UPDATE';
    protected array $supportedAttributes = [self::SHOW, self::LIST, self::CREATE, self::UPDATE];

    protected function canList(Device $subject): void
    {
    }

    protected function canCreate(Device $subject): void
    {
    }

    protected function canShow(Device $subject): void
    {
    }

    protected function canUpdate(Device $subject): void
    {
        $owner = $subject->getOwnerUser();

        if ($owner === null) {
            throw new AccessDeniedException('Predefined devices cannot be modified');
        }

        if ($owner->getId() !== $this->user->getUserIdentifier()) {
            throw new AccessDeniedException('You can only modify your own devices');
        }
    }
}
