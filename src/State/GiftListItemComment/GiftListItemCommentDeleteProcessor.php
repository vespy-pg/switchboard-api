<?php

namespace App\State\GiftListItemComment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\GiftListItemComment;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class GiftListItemCommentDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof GiftListItemComment) {
            return $data;
        }

        // Set createdByUserId and ownerUserId to current user
        $data->setRemovedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return $data;
    }
}
