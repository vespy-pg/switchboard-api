<?php

namespace App\State\GiftListItem;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\GiftListItem;
use App\Service\GiftCurrencyChangeService;
use Doctrine\ORM\EntityManagerInterface;

class GiftListItemUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GiftCurrencyChangeService $currencyChangeService
    ) {
    }

    /**
     * @param GiftListItem $data
     * @param Operation $operation
     * @param array $uriVariables
     * @param array $context
     * @return GiftListItem
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Check if entity is managed before trying to get original data
        if (!$this->entityManager->contains($data)) {
            // Entity not managed yet - just persist normally
            $this->entityManager->persist($data);
            $this->entityManager->flush();
            return $data;
        }

        // Get the original entity from the database to detect currency changes
        $originalEntity = $this->entityManager->getUnitOfWork()->getOriginalEntityData($data);

        // Safely get currency codes
        $originalCurrencyCode = null;
        if (isset($originalEntity['currency']) && $originalEntity['currency'] instanceof \App\Entity\Currency) {
            $originalCurrencyCode = $originalEntity['currency']->getCode();
        }

        $newCurrencyCode = $data->getCurrency()?->getCode();

        // Check if currency has changed
        $currencyChanged = $originalCurrencyCode !== $newCurrencyCode;

        if ($currencyChanged) {
            // Use the service to handle currency change and reservation recalculation
            $this->currencyChangeService->changeGiftCurrency($data, $data->getCurrency());
        } else {
            // No currency change - just persist normally
            $this->entityManager->persist($data);
            $this->entityManager->flush();
        }

        return $data;
    }
}
