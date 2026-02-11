<?php

namespace App\State\Event;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Event;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class EventCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Event) {
            return $data;
        }

        // Get the current user
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \RuntimeException('User must be authenticated to create an Event');
        }

        // Set createdByUserId and ownerUserId to current user
        $data->setCreatedByUser($user);

        // Set createdAt timestamp (always set for new entities)
        $data->setCreatedAt(new DateTimeImmutable());

        // Persist and flush
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
