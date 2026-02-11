<?php

namespace App\State\GiftListItem;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\GiftListItem;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class GiftListItemDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof GiftListItem) {
            return $data;
        }

        // Soft delete: set removedAt timestamp
        $data->setRemovedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        return $data;
    }
}
