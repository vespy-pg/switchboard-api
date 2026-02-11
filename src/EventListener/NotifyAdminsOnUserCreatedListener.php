<?php

namespace App\EventListener;

use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Entity\User;
use App\Event\UserCreatedEvent;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class NotifyAdminsOnUserCreatedListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
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

        $notificationType = $this->entityManager
            ->getRepository(NotificationType::class)
            ->find(NotificationType::NOTIFICATION);

        if (!$notificationType || !$notificationType->getIsActive()) {
            return;
        }

        $user = null;
        if ($event->userId) {
            $user = $this->entityManager
                ->getRepository(User::class)
                ->find($event->userId);
        }

        foreach ($this->fetchActiveAdminUsers() as $adminUser) {
            // optional: avoid notifying the same user who created themselves
            if ($user && $adminUser->getId() === $user->getId()) {
                continue;
            }

            $notification = new Notification();
            $notification->setRecipientUser($adminUser);
            $notification->setUser($user);
            $notification->setType($notificationType);

            $notification->setRefNone(false);
            $notification->setRefUser($createdUser);
            $notification->setCreatedAt(new DateTimeImmutable());

            $notification->setPayload([
                'createdUserId' => $createdUser->getId(),
                'email' => $createdUser->getEmail(),
                'isVerified' => $createdUser->getIsVerified(),
            ]);

            $notification->setIsRead(false);
            $notification->setIsAcknowledged(false);

            $this->entityManager->persist($notification);
        }

        $this->entityManager->flush();
    }

    /**
     * @return User[]
     */
    private function fetchActiveAdminUsers(): array
    {
        return $this->userRepository->getAdmins();
    }
}
