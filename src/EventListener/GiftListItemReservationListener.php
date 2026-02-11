<?php

namespace App\EventListener;

use App\Entity\GiftListItemReservation;
use App\Entity\User;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Bundle\SecurityBundle\Security;

class GiftListItemReservationListener
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        // Only handle GiftListItemReservation entities
        if (!$entity instanceof GiftListItemReservation) {
            return;
        }

        // Only set if not already set (allows manual override if needed)
        if ($entity->getReservedByUser() !== null) {
            return;
        }

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $entity->setReservedByUser($user);
        }
    }
}
