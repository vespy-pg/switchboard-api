<?php

namespace App\State\GiftList;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\GiftList;
use App\Entity\GiftListItem;
use App\Entity\GiftListItemStatus;
use App\Entity\User;
use App\Repository\FxShareSlugRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class GiftListCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly FxShareSlugRepository $fxShareSlugRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof GiftList) {
            return $data;
        }

        // Get the current user
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \RuntimeException('User must be authenticated to create a list');
        }

        // Set createdByUserId and ownerUserId to current user
        $data->setCreatedByUser($user);
        $shareSlug = $this->fxShareSlugRepository->generate();
        $data->setShareSlug($shareSlug);

        // Set createdAt timestamp (always set for new entities)
        $data->setCreatedAt(new DateTimeImmutable());
        $data->setIsUnlisted(false);

        // Persist and flush
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
