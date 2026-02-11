<?php

namespace App\Repository;

use App\Entity\Currency;
use App\Entity\FxRate;
use App\Exception\FxRateNotFoundException;
use App\Exception\FxShareSlugNotFoundException;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

class FxShareSlugRepository extends ServiceEntityRepository
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
    public function generate(): string
    {
        $sql = 'SELECT * FROM app.fx_share_slug_reserve()';

        $result = $this->connection->fetchOne($sql);
        if (!$result) {
            throw new FxShareSlugNotFoundException();
        }
        return $result;
    }
}
