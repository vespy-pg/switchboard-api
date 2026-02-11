<?php

namespace App\Service;

use App\Entity\Currency;
use App\Entity\GiftListItem;
use App\Entity\GiftListItemReservation;
use App\Exception\FxRateNotFoundException;
use App\Repository\FxRateRepository;
use App\Repository\GiftListItemReservationRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class GiftCurrencyChangeService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GiftListItemReservationRepository $reservationRepository,
        private FxRateRepository $fxRateRepository,
        private Connection $connection
    ) {
    }

    /**
     * Change the currency of a gift item and recalculate all active amount reservations.
     *
     * @param GiftListItem $giftItem
     * @param Currency|null $newCurrency
     * @return void
     * @throws FxRateNotFoundException
     * @throws \Doctrine\DBAL\Exception
     */
    public function changeGiftCurrency(GiftListItem $giftItem, ?Currency $newCurrency): void
    {
        $changeTime = new DateTimeImmutable();
        $newCurrencyCode = $newCurrency?->getCode();

        // Start transaction
        $this->connection->beginTransaction();

        try {
            // Update gift item currency
            $giftItem->setCurrency($newCurrency);

            // Load active amount reservations
            $reservations = $this->reservationRepository->findActiveAmountReservations($giftItem);

            // Recalculate each reservation
            foreach ($reservations as $reservation) {
                $this->recalculateReservation($reservation, $newCurrency, $newCurrencyCode, $changeTime);
            }

            // Flush all changes
            $this->entityManager->flush();

            // Commit transaction
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * Recalculate a single reservation based on the new gift currency.
     *
     * @param GiftListItemReservation $reservation
     * @param Currency|null $newCurrency
     * @param string|null $newCurrencyCode
     * @param DateTimeImmutable $changeTime
     * @return void
     * @throws FxRateNotFoundException
     * @throws \Doctrine\DBAL\Exception
     */
    private function recalculateReservation(
        GiftListItemReservation $reservation,
        ?Currency $newCurrency,
        ?string $newCurrencyCode,
        DateTimeImmutable $changeTime
    ): void {
        $reservationCurrencyCode = $reservation->getCurrency()?->getCode();
        $shareAmount = $reservation->getShareAmount();

        // Rule 2: If new gift currency is NULL
        if ($newCurrencyCode === null) {
            $reservation->setGiftCurrencySnapshot(null);
            $reservation->setShareAmountInGiftCurrency(null);
            $reservation->setFxRateDate(null);
            return;
        }

        // Rule 3: If reservation currency equals new gift currency
        if ($reservationCurrencyCode === $newCurrencyCode) {
            $reservation->setGiftCurrencySnapshot($newCurrency);
            $reservation->setShareAmountInGiftCurrency($shareAmount);
            $reservation->setFxRateDate(null); // No FX conversion needed
            return;
        }

        // Rule 4: Different currencies - need FX conversion
        if ($reservationCurrencyCode === null) {
            // Edge case: reservation has amount but no currency - treat as same currency
            $reservation->setGiftCurrencySnapshot($newCurrency);
            $reservation->setShareAmountInGiftCurrency($shareAmount);
            $reservation->setFxRateDate(null);
            return;
        }

        // Call repository to resolve FX rate
        $fxRate = $this->fxRateRepository->resolveFxRate(
            $reservationCurrencyCode,
            $newCurrencyCode,
            $changeTime
        );

        // Convert amount
        $convertedAmount = round($shareAmount * $fxRate->getRate(), 2);

        // Update reservation
        $reservation->setGiftCurrencySnapshot($newCurrency);
        $reservation->setShareAmountInGiftCurrency($convertedAmount);
        $reservation->setFxRateDate($fxRate->getRateDate());
    }
}
