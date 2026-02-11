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
    name: 'app:locale:sync:currencies',
    description: 'Fetch CLDR currency data from GitHub and upsert app.tbl_currency'
)]
final class SyncCurrenciesCommand extends Command
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

        // Names (English) live in cldr-numbers-full (NOT localenames)
        $currenciesJson = $this->fetchJson(
            "{$cldrBaseUrl}/cldr-numbers-full/main/en/currencies.json"
        );

        // Digits + region currency timelines live in cldr-core supplemental
        $currencyDataJson = $this->fetchJson(
            "{$cldrBaseUrl}/cldr-core/supplemental/currencyData.json"
        );

        $currencyNames = $currenciesJson['main']['en']['numbers']['currencies'] ?? null;

        if (!is_array($currencyNames) || count($currencyNames) < 50) {
            throw new \RuntimeException(
                'Unexpected CLDR currencies.json structure (currency names not found or too small). ' .
                'Path tried: main.en.numbers.currencies'
            );
        }

        $fractions = $currencyDataJson['supplemental']['currencyData']['fractions'] ?? [];
        $regions = $currencyDataJson['supplemental']['currencyData']['region'] ?? [];

        if (!is_array($fractions) || !is_array($regions)) {
            throw new \RuntimeException('Unexpected CLDR currencyData.json structure (fractions/region missing).');
        }

        $activeCurrencyCodes = $this->buildActiveCurrencyCodeSet($regions);

        $upsertSql = <<<'SQL'
INSERT INTO app.tbl_currency (
    currency_code,
    currency_name_en,
    minor_units,
    is_active,
    is_featured
) VALUES (
    :currency_code,
    :currency_name_en,
    :minor_units,
    :is_active,
    false
)
ON CONFLICT (currency_code) DO UPDATE SET
    currency_name_en = EXCLUDED.currency_name_en,
    minor_units = EXCLUDED.minor_units,
    is_active = EXCLUDED.is_active
SQL;

        $generatedCurrencyCodes = [];
        $upsertedCount = 0;

        $this->connection->beginTransaction();
        try {
            foreach ($currencyNames as $currencyCodeRaw => $currencyInfo) {
                $currencyCode = strtoupper((string) $currencyCodeRaw);

                if (!preg_match('/^[A-Z]{3}$/', $currencyCode)) {
                    continue;
                }

                if (!is_array($currencyInfo)) {
                    continue;
                }

                $currencyNameEn = $currencyInfo['displayName'] ?? null;

                if (!$currencyNameEn) {
                    continue;
                }

                $minorUnits = $this->resolveMinorUnits($currencyCode, $fractions);

                // Active means: appears as a currently used tender currency in at least one region in CLDR region timelines
                $isActive = isset($activeCurrencyCodes[$currencyCode]);

                $generatedCurrencyCodes[] = $currencyCode;

                $this->connection->executeStatement($upsertSql, [
                    'currency_code' => $currencyCode,
                    'currency_name_en' => $currencyNameEn,
                    'minor_units' => $minorUnits,
                    'is_active' => $isActive ? 'true' : 'false',
                ]);

                $upsertedCount++;
            }

            $output->writeln('Generated currency codes: ' . count($generatedCurrencyCodes));
            $output->writeln("Upserted rows: {$upsertedCount}");

            // Safety guard: avoid deactivating everything if something went wrong upstream
            $uniqueCodesCount = count(array_unique($generatedCurrencyCodes));
            if ($uniqueCodesCount >= 150) {
                $this->deactivateMissingRows($generatedCurrencyCodes);
            } else {
                $output->writeln("Skip deactivation (too few generated codes: {$uniqueCodesCount}).");
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        $output->writeln('Currencies synced.');
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
     * CLDR currency fractions map:
     * - exact codes have digits
     * - DEFAULT also exists
     */
    private function resolveMinorUnits(string $currencyCode, array $fractions): int
    {
        if (isset($fractions[$currencyCode]['digits'])) {
            return (int) $fractions[$currencyCode]['digits'];
        }

        if (isset($fractions['DEFAULT']['digits'])) {
            return (int) $fractions['DEFAULT']['digits'];
        }

        return 2;
    }

    /**
     * Build a set of currency codes that are "currently in use" anywhere:
     * - _currency matches
     * - _to is absent/empty
     * - _tender is not "false" (missing/empty => true)
     */
    private function buildActiveCurrencyCodeSet(array $regions): array
    {
        $activeCurrencyCodes = [];

        foreach ($regions as $regionCode => $regionData) {
            // CLDR sometimes stores either:
            // - regionCode => [ {..}, {..} ]
            // - regionCode => { "currency": [ {..}, {..} ] }
            // - regionCode => { "currency": {..single..} }
            $currencyPeriods = $this->normalizeRegionCurrencyPeriods($regionData);

            foreach ($currencyPeriods as $currencyPeriod) {
                if (!is_array($currencyPeriod)) {
                    continue;
                }

                $currencyCode = array_keys($currencyPeriod)[0] ?? null;
                if (!is_string($currencyCode) || !preg_match('/^[A-Z]{3}$/', $currencyCode)) {
                    continue;
                }

                $to = $currencyPeriod[$currencyCode]['_to'] ?? null;

                $tenderRaw = $currencyPeriod[$currencyCode]['_tender'] ?? 'true';
                $isTender = true;

                if ($tenderRaw !== null && $tenderRaw !== '') {
                    $isTender = ($tenderRaw === 'true');
                }

                // Active if: tender AND no "_to"
                if ($isTender && !$to) {
                    $activeCurrencyCodes[$currencyCode] = true;
                }
            }
        }

        return $activeCurrencyCodes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRegionCurrencyPeriods(mixed $regionData): array
    {
        if (!is_array($regionData)) {
            return [];
        }

        // case: ["_someAttr" => "...", "currency" => ...]
        if (array_key_exists('currency', $regionData)) {
            $currencyNode = $regionData['currency'];

            if (is_array($currencyNode)) {
                // currency could be:
                // - list of periods
                // - single period object
                $isList = array_is_list($currencyNode);

                if ($isList) {
                    return $currencyNode;
                }

                return [ $currencyNode ];
            }

            return [];
        }

        // case: already a list of periods
        if (array_is_list($regionData)) {
            return $regionData;
        }

        // case: single object that acts like a period
        if (isset($regionData['_currency'])) {
            return [ $regionData ];
        }

        return [];
    }

    private function deactivateMissingRows(array $generatedCurrencyCodes): void
    {
        $uniqueCodes = array_values(array_unique($generatedCurrencyCodes));

        if (count($uniqueCodes) === 0) {
            return;
        }

        $codesJson = json_encode($uniqueCodes, JSON_THROW_ON_ERROR);

        $sql = <<<'SQL'
UPDATE app.tbl_currency currency
SET is_active = false
WHERE currency.currency_code NOT IN (
    SELECT jsonb_array_elements_text(:currency_codes_json::jsonb)
)
SQL;

        $this->connection->executeStatement($sql, [
            'currency_codes_json' => $codesJson,
        ]);
    }
}
