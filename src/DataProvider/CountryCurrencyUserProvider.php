<?php

namespace App\DataProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\CountryCurrency;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CountryCurrencyUserProvider implements ProviderInterface
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
            throw new \RuntimeException('User must be authenticated to access country currencies');
        }

        // Get the user's country code
        $countryCode = $user->getCountryCode();

        if (!$countryCode) {
            throw new NotFoundHttpException('Current user does not have a country set');
        }

        // Find all country currencies for the user's country
        $repository = $this->entityManager->getRepository(CountryCurrency::class);
        $countryCurrencies = $repository->createQueryBuilder('cc')
            ->where('cc.country = :countryCode')
            ->setParameter('countryCode', $countryCode)
            ->orderBy('cc.isPrimary', 'DESC')
            ->getQuery()
            ->getResult();

        if (empty($countryCurrencies)) {
            throw new NotFoundHttpException('No currencies found for the current user\'s country');
        }

        return $countryCurrencies;
    }
}
