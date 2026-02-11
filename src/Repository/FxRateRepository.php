<?php

namespace App\Repository;

use App\Entity\Currency;
use App\Entity\FxRate;
use App\Exception\FxRateNotFoundException;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

class FxRateRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private Connection $connection
    ) {
        parent::__construct($registry, FxRate::class);
    }

    /**
     * Resolve FX rate using the database function app.fx_resolve_rate.
     *
     * @param string $fromCurrencyCode
     * @param string $toCurrencyCode
     * @param DateTimeImmutable $asOfDate
     * @param int $maxDepth
     * @return FxRate|null FxRate entity with populated rate, rateDate, and Currency relations (or null if not found)
     * @throws \Doctrine\DBAL\Exception
     */
    public function resolveFxRate(
        string $fromCurrencyCode,
        string $toCurrencyCode,
        DateTimeImmutable $asOfDate,
        int $maxDepth = 5
    ): FxRate {
        $sql = 'SELECT rate, fx_rate_date FROM app.fx_resolve_rate(:as_of_date, :from_code, :to_code, :max_depth)';

        $result = $this->connection->fetchAssociative($sql, [
            'as_of_date' => $asOfDate->format('Y-m-d'),
            'from_code' => $fromCurrencyCode,
            'to_code' => $toCurrencyCode,
            'max_depth' => $maxDepth,
        ]);

        if ($result === false) {
            throw FxRateNotFoundException::forCurrencyPair(
                $fromCurrencyCode,
                $toCurrencyCode,
                $asOfDate
            );
        }

        // Get Currency entities
        $fromCurrency = $this->getEntityManager()
            ->getRepository(Currency::class)
            ->findOneBy(['code' => $fromCurrencyCode]);

        $toCurrency = $this->getEntityManager()
            ->getRepository(Currency::class)
            ->findOneBy(['code' => $toCurrencyCode]);

        // Create FxRate entity
        $fxRate = new FxRate();
        $fxRate->setRate($result['rate']);
        $fxRate->setRateDate(new DateTimeImmutable($result['fx_rate_date']));
        $fxRate->setFromCurrency($fromCurrency);
        $fxRate->setToCurrency($toCurrency);

        return $fxRate;
    }
}
