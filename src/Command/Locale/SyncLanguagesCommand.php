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
    name: 'app:locale:sync:languages',
    description: 'Fetch CLDR (official languages per territory) and IANA registry and upsert app.tbl_language'
)]
final class SyncLanguagesCommand extends Command
{
    private $debugEnabled = false;

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

        $cldrBaseUrl = rtrim((string) $_ENV['CLDR_URL'], '/') . '/cldr-json';

        $likelySubtagsJson = $this->fetchJson("{$cldrBaseUrl}/cldr-core/supplemental/likelySubtags.json");
        $territoryInfoJson = $this->fetchJson("{$cldrBaseUrl}/cldr-core/supplemental/territoryInfo.json");

        $englishDisplayNamesJson = $this->fetchJson("{$cldrBaseUrl}/cldr-localenames-full/main/en/languages.json");
        $englishTerritoriesJson = $this->fetchJson("{$cldrBaseUrl}/cldr-localenames-full/main/en/territories.json");

        $ianaRegistryText = $this->fetchText('https://www.iana.org/assignments/language-subtag-registry/language-subtag-registry');
        $deprecatedRegionSubtags = $this->extractDeprecatedRegionSubtags($ianaRegistryText);

        $generatedRows = $this->generateRowsFromOfficialTerritoryLanguages(
            $territoryInfoJson,
            $likelySubtagsJson,
            $englishDisplayNamesJson,
            $englishTerritoriesJson,
            $cldrBaseUrl,
            $deprecatedRegionSubtags
        );

        $generatedLanguageCodes = [];

        $this->connection->beginTransaction();
        try {
            $upsertSql = <<<'SQL'
INSERT INTO app.tbl_language (
    language_code,
    language_base_code,
    language_name_en,
    language_name_native,
    is_active,
    is_featured
) VALUES (
    :language_code,
    :language_base_code,
    :language_name_en,
    :language_name_native,
    :is_active,
    false
)
ON CONFLICT (language_code) DO UPDATE SET
    language_base_code = EXCLUDED.language_base_code,
    language_name_en = EXCLUDED.language_name_en,
    language_name_native = EXCLUDED.language_name_native,
    is_active = EXCLUDED.is_active
SQL;

            foreach ($generatedRows as $generatedRow) {
                $languageCode = $generatedRow['language_code'];
                $generatedLanguageCodes[] = $languageCode;

                $this->connection->executeStatement(
                    $upsertSql,
                    [
                        'language_code' => $languageCode,
                        'language_base_code' => strtoupper($generatedRow['language_base_code']),
                        'language_name_en' => $generatedRow['language_name_en'],
                        'language_name_native' => $generatedRow['language_name_native'],
                        'is_active' => $generatedRow['is_active'] ? 'true' : 'false',
                    ]
                );
            }

            // Safety guard: don't deactivate everything if parsing fails.
            $uniqueCount = count(array_unique($generatedLanguageCodes));
            if ($uniqueCount >= 50) {
                $this->deactivateMissingRows($generatedLanguageCodes);
            } else {
                $output->writeln("Skip deactivation (too few generated languages: {$uniqueCount}).");
            }

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
            throw new RuntimeException("HTTP {$statusCode} for {$url}. Body starts with: " . substr($content, 0, 200));
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
            throw new RuntimeException(
                "Invalid JSON for {$url}. content-type='{$contentType}'. Body starts with: " . substr($content, 0, 200)
            );
        }

        return $decoded;
    }

    private function fetchText(string $url): string
    {
        $response = $this->httpClient->request('GET', $url);
        return $response->getContent(false);
    }

    /**
     * Returns set-like array: ['SU' => true, 'AN' => true, ...]
     */
    private function extractDeprecatedRegionSubtags(string $ianaRegistryText): array
    {
        $deprecatedRegionSubtags = [];
        $records = preg_split('/%%\s*/', $ianaRegistryText);
        foreach ($records as $record) {
            $record = trim((string) $record);
            if ($record === '') {
                continue;
            }

            if (!preg_match('/^Type:\s*region\s*$/mi', $record)) {
                continue;
            }

            if (!preg_match('/^Subtag:\s*([A-Z0-9]{2,3})\s*$/mi', $record, $matches)) {
                continue;
            }

            $regionSubtag = strtoupper($matches[1]);

            if (preg_match('/^Deprecated:\s*(.+)\s*$/mi', $record)) {
                $deprecatedRegionSubtags[$regionSubtag] = true;
            }
        }

        return $deprecatedRegionSubtags;
    }

    /**
     * Build language rows from CLDR territoryInfo officialStatus languages only.
     *
     * - Emits language_code in your preferred lowercase BCP47-ish format.
     * - Prefers script-aware likelySubtags if available for ll_CC.
     * - Marks inactive if any region subtag is deprecated in IANA registry.
     */
    private function generateRowsFromOfficialTerritoryLanguages(
        array $territoryInfoJson,
        array $likelySubtagsJson,
        array $englishDisplayNamesJson,
        array $englishTerritoriesJson,
        string $cldrBaseUrl,
        array $deprecatedRegionSubtags
    ): array {
        $likelySubtagsMap = $likelySubtagsJson['supplemental']['likelySubtags'] ?? [];
        $englishLanguagesMap = $englishDisplayNamesJson['main']['en']['localeDisplayNames']['languages'] ?? [];
        $englishTerritoriesMap = $englishTerritoriesJson['main']['en']['localeDisplayNames']['territories'] ?? [];

        $territoryInfoMap = $territoryInfoJson['supplemental']['territoryInfo'] ?? null;

        if (!is_array($territoryInfoMap) || count($territoryInfoMap) < 50) {
            throw new RuntimeException('Unexpected CLDR territoryInfo structure (missing or too small).');
        }

        $rowsByLanguageCode = [];
        foreach ($territoryInfoMap as $territoryCodeRaw => $territoryInfo) {
            $territoryCode = strtoupper((string) $territoryCodeRaw);
            if (
                in_array(
                    $territoryCode,
                    array_filter(explode(',', $_ENV['APP_COUNTRY_CODES_NON_PRODUCTION'] ?? ''))
                )
            ) {
                continue;
            }
            $this->debug('processing debug territory info', $territoryInfo);
            // Only real countries (skip 001/150 etc)
            if (preg_match('/^[A-Z]{2}$/', $territoryCode) !== 1) {
                $this->debug('territory code not 2 letters');
                continue;
            }

            if (!is_array($territoryInfo)) {
                $this->debug('$territoryInfo not an array');
                continue;
            }

            $languagePopulation = $territoryInfo['languagePopulation'] ?? null;
            if (!is_array($languagePopulation)) {
                $this->debug('$languagePopulation not an array');
                continue;
            }
            $this->debug($territoryCode, $territoryInfo);
            foreach ($languagePopulation as $languageSubtagRaw => $languageInfo) {
                $languageSubtag = str_replace('_', '-', strtolower((string) $languageSubtagRaw));
                $this->debug('processing language population', $languageSubtag, $languageInfo);

                if (preg_match('/^[a-z]{2,3}(?:-[a-z]{4})?$/', $languageSubtag) !== 1) {
                    $this->debug('$languageSubtag not 2-3 letters');
                    continue;
                }

                if (!is_array($languageInfo)) {
                    $this->debug('$languageInfo not an array');
                    continue;
                }
                // Only official languages
                if ((!in_array($languageInfo['_officialStatus'] ?? null, ['de_facto_official',  'official']))) {
                    $this->debug('language code not official');
                    continue;
                }
                $languageCode = $this->buildBestLanguageCodeForTerritory($languageSubtag, $territoryCode, $likelySubtagsMap);

                // Your rule: language_code is lowercase
                $languageCode = strtolower($languageCode);

                $languageNameEn = $this->buildEnglishName($languageCode, $englishLanguagesMap, $englishTerritoriesMap);
                $nativeName = $this->fetchNativeNameForLanguage($languageSubtag, $cldrBaseUrl);

                if ($languageSubtag === 'en') {
                    $nativeName = null;
                }

                $isActive = !$this->languageCodeContainsDeprecatedRegion($languageCode, $deprecatedRegionSubtags);

                $rowsByLanguageCode[$languageCode] = [
                    'language_code' => $languageCode,
                    'language_base_code' => $languageSubtag,
                    'language_name_en' => $languageNameEn,
                    'language_name_native' => $nativeName,
                    'is_active' => $isActive,
                ];
                $this->debug('SUCCESS', $rowsByLanguageCode[$languageCode]);
            }
        }

        return array_values($rowsByLanguageCode);
    }

    /**
     * Prefer likelySubtags for ll_CC so you get scripts when needed (e.g. zh-Hans-CN).
     * If not found, fallback to ll-CC.
     */
    private function buildBestLanguageCodeForTerritory(string $languageSubtagLowercase, string $territoryUppercase, array $likelySubtagsMap): string
    {
        // likelySubtags keys are underscore-separated, with region uppercase.
        // Example keys: "zh", "zh_Hans", "zh_CN", "sr_RS" etc (varies)
        $key = $languageSubtagLowercase . '_' . $territoryUppercase;

        $likely = $likelySubtagsMap[$key] ?? null;

        if (is_string($likely) && $likely !== '') {
            // Example: "zh_Hans_CN" -> "zh-hans-cn"
            return strtolower(str_replace('_', '-', $likely));
        }

        // Fallback: "ll-cc"
        return strtolower($languageSubtagLowercase . '-' . strtolower($territoryUppercase));
    }

    private function languageCodeContainsDeprecatedRegion(string $languageCodeLowercase, array $deprecatedRegionSubtags): bool
    {
        $subtags = explode('-', $languageCodeLowercase);

        if (count($subtags) < 2) {
            return false;
        }

        // BCP47-ish:
        // [0] language (never region)
        $subtagIndex = 1;

        // Optional script subtag at [1]: 4 letters, e.g. "hant", "latn"
        if (isset($subtags[$subtagIndex]) && preg_match('/^[a-z]{4}$/', $subtags[$subtagIndex]) === 1) {
            $subtagIndex++;
        }

        // Optional region subtag at current position: 2 letters (alpha-2) or 3 digits (UN M.49)
        if (!isset($subtags[$subtagIndex])) {
            return false;
        }

        $regionSubtag = $subtags[$subtagIndex];
        $isRegionAlpha = preg_match('/^[a-z]{2}$/', $regionSubtag) === 1;
        $isRegionNumeric = preg_match('/^\d{3}$/', $regionSubtag) === 1;

        if (!$isRegionAlpha && !$isRegionNumeric) {
            return false;
        }

        $regionUppercase = strtoupper($regionSubtag);

        return isset($deprecatedRegionSubtags[$regionUppercase]);
    }

    private function buildEnglishName(string $languageTagLowercase, array $englishLanguagesMap, array $englishTerritoriesMap): string
    {
        $subtags = explode('-', $languageTagLowercase);
        $languageSubtag = $subtags[0];

        $languageName = $englishLanguagesMap[$languageSubtag] ?? $languageSubtag;

        $regionSubtag = null;
        foreach ($subtags as $subtag) {
            if (preg_match('/^[a-z]{2}$/', $subtag) === 1 || preg_match('/^\d{3}$/', $subtag) === 1) {
                $regionSubtag = strtoupper($subtag);
            }
        }

        if ($regionSubtag === null) {
            return $languageName;
        }

        $territoryName = $englishTerritoriesMap[$regionSubtag] ?? $regionSubtag;

        return "{$languageName} ({$territoryName})";
    }

    private function fetchNativeNameForLanguage(string $languageBaseCode, string $cldrBaseUrl): ?string
    {
        // Keep your current choice: native-name sync not implemented now
        return null;
    }

    private function deactivateMissingRows(array $generatedLanguageCodes): void
    {
        $generatedLanguageCodesUnique = array_values(array_unique($generatedLanguageCodes));

        if (count($generatedLanguageCodesUnique) === 0) {
            return;
        }

        $generatedLanguageCodesJson = json_encode($generatedLanguageCodesUnique, JSON_THROW_ON_ERROR);

        $sql = <<<'SQL'
UPDATE app.tbl_language language
SET is_active = false
WHERE language.language_code NOT IN (
    SELECT jsonb_array_elements_text(:generated_language_codes_json::jsonb)
)
SQL;

        $this->connection->executeStatement($sql, [
            'generated_language_codes_json' => $generatedLanguageCodesJson,
        ]);
    }

    private function debug(...$values): void
    {
        if ($this->debugEnabled) {
            dump($values);
        }
    }
}
