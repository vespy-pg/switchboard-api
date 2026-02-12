<?php

namespace App\EventListener;

use App\Entity\User;
use App\Event\UserCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class NotifyAdminsOnUserCreatedListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
//        dump('NotifyAdminsOnUserCreatedListener');
    }

    public function __invoke(UserCreatedEvent $event): void
    {
//        dump('invoking');
        $createdUser = $this->entityManager
            ->getRepository(User::class)
            ->find($event->createdUserId);

        if (!$createdUser) {
            return;
        }
    }
}
