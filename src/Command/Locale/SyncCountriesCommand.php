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
    name: 'app:locale:sync:countries',
    description: 'Fetch CLDR territories from GitHub and upsert app.tbl_country'
)]
final class SyncCountriesCommand extends Command
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

        $englishTerritoriesJson = $this->fetchJson("{$cldrBaseUrl}/cldr-localenames-full/main/en/territories.json");

        // Optional: pick ONE locale for "native" column (example: Polish). Or keep null for all.
        $nativeLocale = null; // 'pl';
        $nativeTerritoriesJson = null;

        if ($nativeLocale !== null) {
            $nativeTerritoriesJson = $this->fetchJson("{$cldrBaseUrl}/cldr-localenames-full/main/{$nativeLocale}/territories.json");
        }

        $englishTerritoriesMap = $englishTerritoriesJson['main']['en']['localeDisplayNames']['territories'] ?? [];
        $nativeTerritoriesMap = $nativeTerritoriesJson !== null
            ? ($nativeTerritoriesJson['main'][$nativeLocale]['localeDisplayNames']['territories'] ?? [])
            : [];

        $upsertSql = <<<'SQL'
INSERT INTO app.tbl_country (
    country_code,
    country_name_en,
    country_name_native,
    is_active,
    is_featured,
    replaced_by_country_code
) VALUES (
    :country_code,
    :country_name_en,
    :country_name_native,
    true,
    false,
    NULL
)
ON CONFLICT (country_code) DO UPDATE SET
    country_name_en = EXCLUDED.country_name_en,
    country_name_native = EXCLUDED.country_name_native
SQL;

        $generatedCountryCodes = [];

        $this->connection->beginTransaction();
        try {
            foreach ($englishTerritoriesMap as $territoryCodeRaw => $territoryNameEn) {
                $territoryCode = (string) $territoryCodeRaw;

                if (!preg_match('/^[A-Z]{2}$/', $territoryCode)) {
                    continue;
                }

                if (
                    in_array(
                        $territoryCode,
                        array_filter(explode(',', $_ENV['APP_COUNTRY_CODES_NON_PRODUCTION'] ?? ''))
                    )
                ) {
                    continue;
                }

                // CLDR includes a few pseudo regions sometimes; this keeps it strict alpha-2.
                $countryCode = $territoryCode;
                $generatedCountryCodes[] = $countryCode;

                $countryNameNative = null;
                if ($nativeLocale !== null) {
                    $countryNameNative = $nativeTerritoriesMap[$countryCode] ?? null;
                }

                $this->connection->executeStatement($upsertSql, [
                    'country_code' => $countryCode,
                    'country_name_en' => $territoryNameEn,
                    'country_name_native' => $countryNameNative,
                ]);
            }

            $this->deactivateMissingRows($generatedCountryCodes);

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        $output->writeln('Done.');
        return Command::SUCCESS;
    }

    private function fetchJson(string $url): array
    {
        $response = $this->httpClient->request('GET', $url);

        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);

        if ($statusCode !== 200) {
            throw new \RuntimeException("HTTP {$statusCode} for {$url}. Body starts with: " . substr($content, 0, 200));
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException("Invalid JSON for {$url}. Body starts with: " . substr($content, 0, 200));
        }

        return $decoded;
    }

    private function deactivateMissingRows(array $generatedCountryCodes): void
    {
        $generatedCountryCodesUnique = array_values(array_unique($generatedCountryCodes));

        if (count($generatedCountryCodesUnique) === 0) {
            return;
        }

        $generatedCountryCodesJson = json_encode($generatedCountryCodesUnique, JSON_THROW_ON_ERROR);

        $sql = <<<'SQL'
UPDATE app.tbl_country country
SET is_active = false
WHERE country.country_code NOT IN (
    SELECT jsonb_array_elements_text(:generated_country_codes_json::jsonb)
)
SQL;

        $this->connection->executeStatement($sql, [
            'generated_country_codes_json' => $generatedCountryCodesJson,
        ]);
    }
}
