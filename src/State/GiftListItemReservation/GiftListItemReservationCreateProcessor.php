<?php

namespace App\State\GiftListItemReservation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\GiftListItemReservation;
use App\Entity\GiftListItemReservationStatus;
use App\Repository\FxRateRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use http\Exception\InvalidArgumentException;

class GiftListItemReservationCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FxRateRepository $fxRateRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof GiftListItemReservation) {
            return $data;
        }

        $data->setCreatedAt(new DateTimeImmutable());
        $data->setStatus($this->entityManager->getReference(GiftListItemReservationStatus::class, GiftListItemReservationStatus::ACTIVE));
        $data->setGiftCurrencySnapshot($data->getGiftListItem()->getCurrency());
        if (!$data->getGiftCurrencySnapshot()) {
            $data->setGiftCurrencySnapshot($data->getGiftListItem()->getCurrency());
        }
        if ($data->getSharePercent() || !$data->getCurrencyCode()) {
            $data->setCurrency($data->getGiftListItem()->getCurrency());
        }
        $fxRateValue = 1;
        if ($data->getCurrencyCode() !== $data->getGiftListItem()->getCurrencyCode()) {
            $fxRate = $this->fxRateRepository->resolveFxRate(
                $data->getCurrencyCode(),
                $data->getGiftListItem()->getCurrencyCode(),
                new DateTimeImmutable()
            );
            $fxRateValue = (float)$fxRate->getRate();
            $data->setFxRateDate($fxRate->getRateDate());
        }
        if ($data->getSharePercent() && !$data->getGiftListItem()->getPriceTo()) {
            throw new InvalidArgumentException('Can not reserve percent when gift has no price');
        }
        if ($data->getSharePercent()) {
            $data->setSharePercent(min($data->getSharePercent(), 100));
        }
        $shareValue = $data->getShareAmount() ?: $data->getSharePercent() * (float)$data->getGiftListItem()->getPriceTo() / 100;
        $shareValue = $shareValue * $fxRateValue;
        if ($data->getGiftListItem()->getPriceTo()) {
            $shareValue = min($shareValue, $data->getGiftListItem()->getPriceTo());
        }
        $data->setShareAmountInGiftCurrency($shareValue);

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
