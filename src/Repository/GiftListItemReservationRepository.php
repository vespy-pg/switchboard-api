<?php

namespace App\Repository;

use App\Entity\GiftListItem;
use App\Entity\GiftListItemReservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GiftListItemReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GiftListItemReservation::class);
    }

    /**
     * Find all active amount-based reservations for a gift item.
     * Active = removed_at IS NULL
     * Amount-based = share_amount IS NOT NULL
     *
     * @param GiftListItem $giftItem
     * @return GiftListItemReservation[]
     */
    public function findActiveAmountReservations(GiftListItem $giftItem): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.giftListItem = :giftItem')
            ->andWhere('r.removedAt IS NULL')
            ->andWhere('r.shareAmount IS NOT NULL')
            ->setParameter('giftItem', $giftItem)
            ->getQuery()
            ->getResult();
    }
}
