<?php

namespace App\DataProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\GiftList;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GiftListDefaultProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Get the current user
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \RuntimeException('User must be authenticated to access default gift list');
        }

        // Find the first non-removed gift list owned by the current user
        $repository = $this->entityManager->getRepository(GiftList::class);
        $giftList = $repository->createQueryBuilder('gl')
            ->where('gl.ownerUser = :userId')
            ->andWhere('gl.removedAt IS NULL')
            ->setParameter('userId', $user->getId())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$giftList) {
            throw new NotFoundHttpException('No default gift list found for the current user');
        }

        return $giftList;
    }
}
