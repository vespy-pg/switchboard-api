<?php

declare(strict_types=1);

namespace App\Command\Locale;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:locale:sync:country-language-hints',
    description: 'Populate app.tbl_country_language_hint from app.tbl_language (region-subtag locales only)'
)]
final class SyncCountryLanguageHintsCommand extends Command
{
    private $debugEnabled = false;

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $activeCountryCodes = $this->fetchExistingCountryCodeSet();
        $languageRows = $this->fetchActiveLanguageRows();

        $hintRowsByKey = [];

        foreach ($languageRows as $languageRow) {
            $languageCode = (string) ($languageRow['language_code'] ?? '');
            if ($languageCode === '') {
                continue;
            }

            $countryCode = $this->extractCountryCodeFromLanguageTag($languageCode);

            // Skip:
            // - no region
            // - numeric regions like 419 (you said skip)
            if ($countryCode === null) {
                continue;
            }

            if (!isset($activeCountryCodes[$countryCode])) {
                continue;
            }

            $weight = $this->computeHintWeight($languageCode);

            $key = $countryCode . '|' . $languageCode;

            // Keep the maximum weight if duplicates arise
            if (!isset($hintRowsByKey[$key]) || $weight > $hintRowsByKey[$key]['weight']) {
                $hintRowsByKey[$key] = [
                    'country_code' => $countryCode,
                    'language_code' => $languageCode,
                    'weight' => $weight,
                ];
            }
        }

        $hintRows = array_values($hintRowsByKey);

        $output->writeln('Generated hint rows: ' . count($hintRows));

        // Safety guard: avoid wiping table if something clearly went wrong
        if (count($hintRows) < 50) {
            throw new \RuntimeException('Too few country-language hint rows generated. Refusing to overwrite.');
        }

        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement('TRUNCATE TABLE app.tbl_country_language_hint');

            $insertSql = <<<'SQL'
INSERT INTO app.tbl_country_language_hint (
    country_code,
    language_code,
    weight
) VALUES (
    :country_code,
    :language_code,
    :weight
)
SQL;

            $insertedRows = 0;

            foreach ($hintRows as $hintRow) {
                $this->connection->executeStatement($insertSql, [
                    'country_code' => $hintRow['country_code'],
                    'language_code' => $hintRow['language_code'],
                    'weight' => $hintRow['weight'],
                ]);

                $insertedRows++;
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        $output->writeln("Inserted rows: {$insertedRows}");
        $output->writeln('Country-language hint mappings refreshed.');
        return Command::SUCCESS;
    }

    /**
     * Returns set-like array: ['ES' => true, 'CN' => true, ...]
     */
    private function fetchExistingCountryCodeSet(): array
    {
        $countryCodeRows = $this->connection->fetchAllAssociative(<<<'SQL'
SELECT country_code
FROM app.tbl_country
SQL);

        $countryCodes = [];

        foreach ($countryCodeRows as $countryCodeRow) {
            $countryCode = (string) ($countryCodeRow['country_code'] ?? '');
            $countryCode = strtoupper($countryCode);

            if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
                continue;
            }

            $countryCodes[$countryCode] = true;
        }

        return $countryCodes;
    }

    /**
     * @return array<int, array{language_code: string}>
     */
    private function fetchActiveLanguageRows(): array
    {
        return $this->connection->fetchAllAssociative(<<<'SQL'
SELECT language_code
FROM app.tbl_language
WHERE is_active = true
SQL);
    }

    /**
     * Extract 2-letter country code (region subtag) from a BCP47-like language tag you store (lowercase).
     *
     * Examples:
     * - "es-es" -> "ES"
     * - "zh-hans-cn" -> "CN"
     * - "sr-latn-rs" -> "RS"
     * - "es-419" -> null (numeric region skipped)
     */
    private function extractCountryCodeFromLanguageTag(string $languageCode): ?string
    {
        $subtags = explode('-', strtolower($languageCode));

        foreach ($subtags as $subtag) {
            // Numeric regions like 419 should be skipped (per your rule)
            if (preg_match('/^\d{3}$/', $subtag) === 1) {
                return null;
            }
        }

        $regionSubtag = null;

        // Region is usually the last 2-letter subtag (but be safe and scan)
        foreach ($subtags as $subtag) {
            if (preg_match('/^[a-z]{2}$/', $subtag) === 1) {
                $regionSubtag = $subtag;
            }
        }

        if ($regionSubtag === null) {
            return null;
        }

        return strtoupper($regionSubtag);
    }

    /**
     * Weighting strategy (simple + predictable):
     * - ll-cc            => 300 (clean locale, best hint)
     * - ll-script-cc     => 200
     * - otherwise        => 100
     */
    private function computeHintWeight(string $languageCode): int
    {
        $languageCodeLowercase = strtolower($languageCode);

        if (preg_match('/^[a-z]{2,3}-[a-z]{2}$/', $languageCodeLowercase) === 1) {
            return 300;
        }

        if (preg_match('/^[a-z]{2,3}-[a-z]{4}-[a-z]{2}$/', $languageCodeLowercase) === 1) {
            return 200;
        }

        return 100;
    }

    private function debug(...$values): void
    {
        if ($this->debugEnabled) {
            dump($values);
        }
    }
}
