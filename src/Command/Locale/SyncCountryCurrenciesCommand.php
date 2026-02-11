<?php

declare(strict_types=1);

namespace App\Command\Locale;

use Doctrine\DBAL\Connection;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:locale:sync:country-currencies',
    description: 'Populate app.tbl_country_currency from CLDR currencyData.region (current tender only)'
)]
final class SyncCountryCurrenciesCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!($_ENV['CLDR_URL'] ?? null)) {
            throw new RuntimeException('CLDR_URL environment variable is not defined.');
        }
        $cldrBaseUrl = rtrim($_ENV['CLDR_URL'], '/') . '/cldr-json';

        $currencyDataJson = $this->fetchJson(
            "{$cldrBaseUrl}/cldr-core/supplemental/currencyData.json"
        );

        $regions = $currencyDataJson['supplemental']['currencyData']['region'] ?? null;

        if (!is_array($regions) || count($regions) < 50) {
            throw new \RuntimeException('Unexpected CLDR currencyData.region structure (missing or too small).');
        }

        // Build mapping: country_code => [ ['code' => 'EUR', 'from' => '1999-01-01'], ... current tender ... ]
        $countryCurrencies = $this->extractCurrentTenderCurrenciesByCountry($regions);

        $this->connection->beginTransaction();
        try {
            // Derived table: easiest is to fully refresh
            $this->connection->executeStatement('TRUNCATE TABLE app.tbl_country_currency');

            $insertSql = <<<'SQL'
INSERT INTO app.tbl_country_currency (
    country_code,
    currency_code,
    is_primary
) VALUES (
    :country_code,
    :currency_code,
    :is_primary
)
SQL;

            $insertedRows = 0;

            foreach ($countryCurrencies as $countryCode => $currencyPeriods) {
                // Choose primary: latest _from among current tender currencies
                $primaryCurrencyCode = $this->pickPrimaryCurrencyCode($currencyPeriods);
                foreach ($currencyPeriods as $currencyPeriod) {
                    $currencyCode = $currencyPeriod['code'];

                    $isPrimary = ($currencyCode === $primaryCurrencyCode);
                    $this->connection->executeStatement($insertSql, [
                        'country_code' => $countryCode,
                        'currency_code' => $currencyCode,
                        'is_primary' => $isPrimary ? 'true' : 'false',
                    ]);

                    $insertedRows++;
                }
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            dump($exception);
            $this->connection->rollBack();
            throw $exception;
        }

        $output->writeln('Country-currency mappings refreshed.');
        return Command::SUCCESS;
    }

    private function fetchJson(string $url): array
    {
        $response = $this->httpClient->request('GET', $url);

        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);

        if ($statusCode !== 200) {
            throw new \RuntimeException(
                "HTTP {$statusCode} for {$url}. Body starts with: " . substr($content, 0, 200)
            );
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException(
                "Invalid JSON for {$url}. Body starts with: " . substr($content, 0, 200)
            );
        }

        return $decoded;
    }

    /**
     * Returns: ['ES' => [ ['code' => 'EUR', 'from' => '1999-01-01'], ...], ...]
     *
     * Rules:
     * - only region keys matching ^[A-Z]{2}$ (skip numeric like 419)
     * - include only periods where:
     *   - _to absent/empty
     *   - _tender != "false" (missing/empty treated as true)
     */
    private function extractCurrentTenderCurrenciesByCountry(array $regions): array
    {
        $result = [];

        foreach ($regions as $regionCodeRaw => $regionData) {
            $countryCode = (string) $regionCodeRaw;

            if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
                continue;
            }

            if (
                in_array(
                    $countryCode,
                    array_filter(explode(',', $_ENV['APP_COUNTRY_CODES_NON_PRODUCTION'] ?? ''))
                )
            ) {
                continue;
            }

            $currencyPeriods = $this->normalizeRegionCurrencyPeriods($regionData);

            foreach ($currencyPeriods as $currencyPeriod) {
                if (!is_array($currencyPeriod)) {
                    continue;
                }

                $currencyCode = $currencyPeriod['_currency'] ?? null;
                if (!is_string($currencyCode) || !preg_match('/^[A-Z]{3}$/', $currencyCode)) {
                    continue;
                }

                $to = $currencyPeriod['_to'] ?? null;
                if ($to !== null && $to !== '') {
                    continue; // historical, not current
                }

                $tenderRaw = $currencyPeriod['_tender'] ?? null;

                // tender missing/empty => true, only explicit "false" disables
                if ($tenderRaw === 'false') {
                    continue;
                }

                $from = $currencyPeriod['_from'] ?? null;
                $fromNormalized = is_string($from) ? $from : null;

                $result[$countryCode][] = [
                    'code' => $currencyCode,
                    'from' => $fromNormalized,
                ];
            }

            if (isset($result[$countryCode])) {
                // Deduplicate currency codes within a country (keep max-from)
                $result[$countryCode] = $this->deduplicateByLatestFrom($result[$countryCode]);
            }
        }

        return $result;
    }

    /**
     * Normalize CLDR region currency data into a list of period objects.
     *
     * Common shapes:
     * - region => [ {..}, {..} ]
     * - region => { "currency": [ {..}, {..} ] }
     * - region => { "currency": {..single..} }
     */
    private function normalizeRegionCurrencyPeriods(mixed $regionData): array
    {
        if (!is_array($regionData)) {
            return [];
        }
        $periods = [];
        foreach ($regionData as $regionDatum) {
            if (!is_array($regionDatum)) {
                continue;
            }
            $currencyCode = array_keys($regionDatum)[0] ?? null;
            $periods[] = [
                '_currency' => $currencyCode,
                '_to' => $regionDatum[$currencyCode]['_to'] ?? null,
                '_from' => $regionDatum[$currencyCode]['_from'] ?? null,
                '_tender' => $regionDatum[$currencyCode]['_tender'] ?? null,
            ];
        }
        return $periods;
    }

    /**
     * If the same currency code appears multiple times for a country, keep the one with latest 'from'.
     */
    private function deduplicateByLatestFrom(array $currencyPeriods): array
    {
        $bestByCode = [];

        foreach ($currencyPeriods as $currencyPeriod) {
            $currencyCode = $currencyPeriod['code'];
            $from = $currencyPeriod['from'] ?? null;

            if (!isset($bestByCode[$currencyCode])) {
                $bestByCode[$currencyCode] = $currencyPeriod;
                continue;
            }

            $existingFrom = $bestByCode[$currencyCode]['from'] ?? null;

            // Prefer later dates (YYYY-MM-DD lexical compare works)
            if (is_string($from) && ($existingFrom === null || $from > $existingFrom)) {
                $bestByCode[$currencyCode] = $currencyPeriod;
            }
        }

        return array_values($bestByCode);
    }

    /**
     * Pick primary currency by latest _from date; if none have from, pick alphabetically first.
     */
    private function pickPrimaryCurrencyCode(array $currencyPeriods): string
    {
        $bestCode = null;
        $bestFrom = null;

        foreach ($currencyPeriods as $currencyPeriod) {
            $currencyCode = $currencyPeriod['code'];
            $from = $currencyPeriod['from'] ?? null;

            if ($bestCode === null) {
                $bestCode = $currencyCode;
                $bestFrom = is_string($from) ? $from : null;
                continue;
            }

            if (is_string($from)) {
                if ($bestFrom === null || $from > $bestFrom) {
                    $bestCode = $currencyCode;
                    $bestFrom = $from;
                    continue;
                }
            }

            // Tie-breaker if dates equal/absent: alphabetic
            if ($bestFrom === null && (!is_string($from)) && $currencyCode < $bestCode) {
                $bestCode = $currencyCode;
            }
        }

        return (string) $bestCode;
    }
}
