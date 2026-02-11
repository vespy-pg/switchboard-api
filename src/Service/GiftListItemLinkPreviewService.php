<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\LinkPreview;
use App\Entity\GiftListItemLink;
use Doctrine\ORM\EntityManagerInterface;
use DOMDocument;
use DOMXPath;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final class GiftListItemLinkPreviewService
{
    private const HTTP_MAX_BYTES = 1_500_000; // 1.5 MB
    private const HTTP_MAX_REDIRECTS = 3;

    // Cache policy (simple + pragmatic)
    private const CACHE_OK_TTL_SECONDS = 7 * 24 * 3600;      // 7 days
    private const CACHE_FAILED_TTL_SECONDS = 2 * 3600;       // 2 hours

    private const HTTP_TIMEOUT_SECONDS = 10;
    private const HTTP_IDLE_TIMEOUT_SECONDS = 8;
    private const HTTP_MAX_DURATION_SECONDS = 12;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Main entrypoint:
     * - reads current entity (url + existing preview cache)
     * - validates url (basic + SSRF-ish protection)
     * - returns cached preview if fresh
     * - fetches html + extracts preview
     * - saves preview back to the entity
     */
    public function getOrFetchPreviewForLinkId(string $giftListItemLinkId): array
    {
        $link = $this->fetchLink($giftListItemLinkId);
        if ($link === null) {
            return $this->failResult('failed', 'Link not found');
        }

        $url = $link->getUrl();
        $url = $this->normalizeUrlForFetch($url);

        $validationError = $this->validateUrl($url);
        if ($validationError !== null) {
            $result = $this->failResult('failed', $validationError);
            $this->savePreview($link, $url, null, $result, $this->ttlForStatus($result['status']));
            return $result;
        }

        $cachedResult = $this->readCache($link);
        if ($cachedResult !== null) {
            return $cachedResult;
        }

        $result = $this->fetchPreview($url);

        // Store preview json on the entity.
        $this->savePreview($link, $url, $result['finalUrl'] ?? null, $result, $this->ttlForStatus($result['status']));

        return $result;
    }

    /**
     * Manual refresh / retry button can call this.
     */
    public function forceRefetchPreviewForLinkId(string $giftListItemLinkId): array
    {
        $link = $this->fetchLink($giftListItemLinkId);
        if ($link === null) {
            return $this->failResult('failed', 'Link not found');
        }

        $url = $link->getUrl();
        $url = $this->normalizeUrlForFetch($url);

        $validationError = $this->validateUrl($url);
        if ($validationError !== null) {
            $result = $this->failResult('failed', $validationError);
            $this->savePreview($link, $url, null, $result, $this->ttlForStatus($result['status']));
            return $result;
        }

        $result = $this->fetchPreview($url);
        $this->savePreview($link, $url, $result['finalUrl'] ?? null, $result, $this->ttlForStatus($result['status']));

        return $result;
    }

    private function normalizeUrlForFetch(string $url): string
    {
        $url = trim($url);

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        $path = $parts['path'] ?? null;

        if (!is_string($scheme) || !is_string($host) || !is_string($path)) {
            return $url;
        }

        $query = [];
        if (isset($parts['query']) && is_string($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        // Keep only params that typically change the actual product.
        // Everything tracking-related is dropped.
        $allowedQueryKeys = [
            'offerId',
            'item_id',
            'variant',
            'th',
        ];

        $filteredQuery = [];
        foreach ($allowedQueryKeys as $allowedQueryKey) {
            if (array_key_exists($allowedQueryKey, $query)) {
                $filteredQuery[$allowedQueryKey] = $query[$allowedQueryKey];
            }
        }

        $normalizedUrl = $scheme . '://' . $host . $path;

        if ($filteredQuery !== []) {
            $normalizedUrl .= '?' . http_build_query($filteredQuery);
        }

        return $normalizedUrl;
    }

    // =========================================================================
    // fetchLink
    // =========================================================================

    private function fetchLink(string $giftListItemLinkId): ?GiftListItemLink
    {
        $link = $this->entityManager->find(GiftListItemLink::class, $giftListItemLinkId);

        if ($link === null) {
            return null;
        }

        if ($link->getRemovedAt() !== null) {
            return null;
        }

        return $link;
    }

    // =========================================================================
    // validateUrl (simple + SSRF-ish protection)
    // =========================================================================

    private function validateUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return 'URL is empty';
        }

        if (strlen($url) > 800) {
            return 'URL is too long';
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return 'Invalid URL format';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return 'Only http/https URLs are allowed';
        }

        // Block credentials in URL (abuse / leakage).
        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'Credentials in URL are not allowed';
        }

        $host = (string) ($parts['host'] ?? '');
        if ($host === '') {
            return 'URL host is missing';
        }

        // Basic SSRF protection:
        // - block localhost
        // - resolve DNS and block private ranges
        // NOTE: DNS rebinding is still a thing; this is best-effort.
        if ($this->isHostBlocked($host)) {
            return 'This host is not allowed';
        }

        return null;
    }

    private function isHostBlocked(string $host): bool
    {
        $hostLower = strtolower($host);

        if ($hostLower === 'localhost' || $hostLower === 'localhost.localdomain') {
            return true;
        }

        // If it's an IP literal, validate directly.
        if (filter_var($hostLower, FILTER_VALIDATE_IP)) {
            return $this->isPrivateOrReservedIp($hostLower);
        }

        // Resolve A/AAAA; block if any resolved IP is private/reserved.
        $resolvedIps = [];

        $aRecords = @dns_get_record($hostLower, DNS_A);
        if (is_array($aRecords)) {
            foreach ($aRecords as $record) {
                if (!empty($record['ip']) && is_string($record['ip'])) {
                    $resolvedIps[] = $record['ip'];
                }
            }
        }

        $aaaaRecords = @dns_get_record($hostLower, DNS_AAAA);
        if (is_array($aaaaRecords)) {
            foreach ($aaaaRecords as $record) {
                if (!empty($record['ipv6']) && is_string($record['ipv6'])) {
                    $resolvedIps[] = $record['ipv6'];
                }
            }
        }

        if ($resolvedIps === []) {
            // If host doesn't resolve, treat as blocked to avoid weird internal resolution cases.
            return true;
        }

        foreach ($resolvedIps as $ip) {
            if ($this->isPrivateOrReservedIp($ip)) {
                return true;
            }
        }

        return false;
    }

    private function isPrivateOrReservedIp(string $ip): bool
    {
        // FILTER_FLAG_NO_PRIV_RANGE blocks RFC1918 + RFC4193-ish
        // FILTER_FLAG_NO_RES_RANGE blocks reserved ranges
        // If validation fails with these flags => it IS private/reserved.
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    // =========================================================================
    // readCache (entity properties)
    // =========================================================================

    private function readCache(GiftListItemLink $link): ?array
    {
        $previewDto = $link->getPreviewJson();
        $expiresAt = $link->getPreviewExpiresAt();

        if ($previewDto === null || $expiresAt === null) {
            return null;
        }

        if ($expiresAt->getTimestamp() <= time()) {
            return null;
        }

        return $this->linkPreviewDtoToArray($previewDto);
    }

    private function linkPreviewDtoToArray(LinkPreview $dto): array
    {
        $product = $dto->getProduct();
        $productArray = null;
        if ($product !== null) {
            $decoded = json_decode($product, true);
            $productArray = is_array($decoded) ? $decoded : null;
        }

        return [
            'url' => $dto->getUrl(),
            'title' => $dto->getTitle(),
            'status' => $dto->getStatus(),
            'product' => $productArray,
            'finalUrl' => $dto->getFinalUrl(),
            'imageUrl' => $dto->getImageUrl(),
            'siteName' => $dto->getSiteName(),
            'faviconUrl' => $dto->getFaviconUrl(),
            'description' => $dto->getDescription(),
            'preview' => [
                'title' => $dto->getTitle(),
                'description' => $dto->getDescription(),
                'imageUrl' => $dto->getImageUrl(),
                'siteName' => $dto->getSiteName(),
                'faviconUrl' => $dto->getFaviconUrl(),
            ],
        ];
    }

    // =========================================================================
    // fetchPreview (best-effort HTTP + parsing)
    // =========================================================================

    private function fetchPreview(string $url): array
    {
        $attemptOptionsList = [
            $this->buildHttpOptionsPrimary(),
            $this->buildHttpOptionsSecondary(),
        ];

        $lastResult = null;

        foreach ($attemptOptionsList as $attemptIndex => $options) {
            dump('attempt', $options);
            $result = $this->fetchPreviewAttempt($url, $options);

            $lastResult = $result;

            $status = (string) ($result['status'] ?? 'failed');

            if ($status === 'ok') {
                return $result;
            }

            // Only retry on blocked. Anything else is not worth another request.
            if ($status !== 'blocked') {
                return $result;
            }

            // blocked + no more attempts -> return it
            if ($attemptIndex === count($attemptOptionsList) - 1) {
                return $result;
            }
        }

        return $lastResult ?? $this->failResult('failed', 'Unexpected error', $url);
    }

    private function fetchPreviewAttempt(string $url, array $options): array
    {
        try {
            $response = $this->httpClient->request('GET', $url, $options);

            $statusCode = $response->getStatusCode();
            $headers = $response->getHeaders(false);
            $contentType = $this->firstHeaderValue($headers, 'content-type');

            if ($statusCode >= 400) {
                return $this->failResult(
                    $statusCode === 403 || $statusCode === 429 ? 'blocked' : 'failed',
                    'HTTP error: ' . $statusCode,
                    $url,
                    $this->finalUrlFromResponse($response, $url),
                );
            }

            // Hard block: non-html
            if ($contentType !== null && stripos($contentType, 'text/html') === false && stripos($contentType, 'application/xhtml') === false) {
                return [
                    'status' => 'failed',
                    'url' => $url,
                    'finalUrl' => $this->finalUrlFromResponse($response, $url),
                    'preview' => [
                        'title' => null,
                        'description' => null,
                        'imageUrl' => null,
                        'siteName' => null,
                        'faviconUrl' => null,
                    ],
                    'product' => null,
                    'warnings' => ['Non-HTML content type: ' . $contentType],
                ];
            }

            // Enforce max size
            $content = $response->getContent(false);
            if (strlen($content) > self::HTTP_MAX_BYTES) {
                $content = substr($content, 0, self::HTTP_MAX_BYTES);
            }

            $finalUrl = $this->finalUrlFromResponse($response, $url);

            $parsed = $this->extractFromHtml($content, $finalUrl);
            $parsed['status'] = 'ok';
            $parsed['url'] = $url;
            $parsed['finalUrl'] = $finalUrl;

            return $parsed;
        } catch (TransportExceptionInterface $exception) {
            $message = $exception->getMessage();

            $this->logger->warning('Preview fetch transport error', [
                'url' => $url,
                'error' => $message,
            ]);

            $status = 'failed';

            // Best-effort classification: timeouts on big marketplaces are usually bot mitigation
            if (stripos($message, 'timeout') !== false) {
                $status = 'blocked';
            }

            return $this->failResult($status, 'Transport error: ' . $message, $url);
        } catch (Throwable $exception) {
            $this->logger->error('Preview fetch error', [
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            return $this->failResult('failed', 'Unexpected error', $url);
        }
    }

    private function buildHttpOptionsPrimary(): array
    {
        return [
            'max_redirects' => self::HTTP_MAX_REDIRECTS,
            'timeout' => self::HTTP_TIMEOUT_SECONDS,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'pl-PL,pl;q=0.9,en-US;q=0.8,en;q=0.7',
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
                'Upgrade-Insecure-Requests' => '1',
                'timeout' => self::HTTP_TIMEOUT_SECONDS,
                'idle_timeout' => self::HTTP_IDLE_TIMEOUT_SECONDS,
                'max_duration' => self::HTTP_MAX_DURATION_SECONDS,
            ],
        ];
    }

    private function buildHttpOptionsSecondary(): array
    {
        // Slightly different profile. Some anti-bot setups behave differently.
        return [
            'max_redirects' => self::HTTP_MAX_REDIRECTS,
            'timeout' => self::HTTP_TIMEOUT_SECONDS,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'pl-PL,pl;q=0.9,en-US;q=0.8,en;q=0.7',
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
                'Upgrade-Insecure-Requests' => '1',

                // These sometimes help, sometimes do nothing, rarely harm.
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'none',
                'Sec-Fetch-User' => '?1',
                'timeout' => self::HTTP_TIMEOUT_SECONDS,
                'idle_timeout' => self::HTTP_IDLE_TIMEOUT_SECONDS,
                'max_duration' => self::HTTP_MAX_DURATION_SECONDS,
            ],
        ];
    }

    private function extractFromHtml(string $html, string $finalUrl): array
    {
        $preview = [
            'title' => null,
            'description' => null,
            'imageUrl' => null,
            'siteName' => null,
            'faviconUrl' => null,
        ];

        $warnings = [];

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($html);
        libxml_clear_errors();

        if ($loaded !== true) {
            return [
                'preview' => $preview,
                'product' => null,
                'warnings' => ['Failed to parse HTML'],
            ];
        }

        $xpath = new DOMXPath($dom);

        // --- OpenGraph / Twitter ---
        $preview['title'] = $this->firstMetaContent($xpath, [
            ['property', 'og:title'],
            ['name', 'twitter:title'],
        ]) ?? $this->titleTag($xpath);

        $preview['description'] = $this->firstMetaContent($xpath, [
            ['property', 'og:description'],
            ['name', 'twitter:description'],
            ['name', 'description'],
        ]);

        $preview['imageUrl'] = $this->normalizeMaybeRelativeUrl(
            $finalUrl,
            $this->firstMetaContent($xpath, [
                ['property', 'og:image'],
                ['name', 'twitter:image'],
            ])
        );

        $preview['siteName'] = $this->firstMetaContent($xpath, [
            ['property', 'og:site_name'],
        ]) ?? $this->hostFromUrl($finalUrl);

        $preview['faviconUrl'] = $this->extractFaviconUrl($xpath, $finalUrl);

        // --- Schema.org Product (JSON-LD) best-effort ---
        $product = $this->extractProductFromJsonLd($xpath, $finalUrl);

        return [
            'preview' => $preview,
            'product' => $product,
            'warnings' => $warnings,
        ];
    }

    private function extractFaviconUrl(DOMXPath $xpath, string $finalUrl): ?string
    {
        $iconHref = $this->firstLinkHref($xpath, [
            'icon',
            'shortcut icon',
            'apple-touch-icon',
        ]);

        if ($iconHref !== null) {
            return $this->normalizeMaybeRelativeUrl($finalUrl, $iconHref);
        }

        // Default fallback
        $host = $this->hostFromUrl($finalUrl);
        if ($host === null) {
            return null;
        }

        $scheme = parse_url($finalUrl, PHP_URL_SCHEME);
        if (!is_string($scheme) || $scheme === '') {
            $scheme = 'https';
        }

        return $scheme . '://' . $host . '/favicon.ico';
    }

    private function extractProductFromJsonLd(DOMXPath $xpath, string $finalUrl): ?array
    {
        $scriptNodes = $xpath->query('//script[@type="application/ld+json"]');
        if ($scriptNodes === false || $scriptNodes->length === 0) {
            return null;
        }

        foreach ($scriptNodes as $node) {
            $jsonText = trim((string) $node->textContent);
            if ($jsonText === '') {
                continue;
            }

            $decoded = json_decode($jsonText, true);
            if ($decoded === null) {
                continue;
            }

            $productNode = $this->findFirstSchemaNode($decoded, 'Product');
            if ($productNode === null) {
                continue;
            }

            $name = isset($productNode['name']) && is_string($productNode['name']) ? $productNode['name'] : null;
            $image = null;

            if (isset($productNode['image'])) {
                if (is_string($productNode['image'])) {
                    $image = $productNode['image'];
                } elseif (is_array($productNode['image']) && isset($productNode['image'][0]) && is_string($productNode['image'][0])) {
                    $image = $productNode['image'][0];
                }
            }

            $offers = $productNode['offers'] ?? null;

            $price = null;
            $currency = null;

            if (is_array($offers)) {
                // offers may be object or array
                $offerNode = $offers;
                if (isset($offers[0]) && is_array($offers[0])) {
                    $offerNode = $offers[0];
                }

                if (is_array($offerNode)) {
                    if (isset($offerNode['price']) && (is_string($offerNode['price']) || is_numeric($offerNode['price']))) {
                        $price = (string) $offerNode['price'];
                    }
                    if (isset($offerNode['priceCurrency']) && is_string($offerNode['priceCurrency'])) {
                        $currency = $offerNode['priceCurrency'];
                    }
                }
            }

            return [
                'name' => $name,
                'price' => $price,
                'currency' => $currency,
                'imageUrl' => $this->normalizeMaybeRelativeUrl($finalUrl, $image),
            ];
        }

        return null;
    }

    private function findFirstSchemaNode(mixed $decoded, string $type): ?array
    {
        if (is_array($decoded)) {
            // If it's a list of nodes
            if (isset($decoded[0]) && is_array($decoded[0])) {
                foreach ($decoded as $item) {
                    $found = $this->findFirstSchemaNode($item, $type);
                    if ($found !== null) {
                        return $found;
                    }
                }
                return null;
            }

            // Graph container
            if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
                foreach ($decoded['@graph'] as $graphNode) {
                    $found = $this->findFirstSchemaNode($graphNode, $type);
                    if ($found !== null) {
                        return $found;
                    }
                }
                return null;
            }

            // Single node with @type
            if (isset($decoded['@type'])) {
                $nodeType = $decoded['@type'];

                if (is_string($nodeType) && strcasecmp($nodeType, $type) === 0) {
                    return $decoded;
                }

                if (is_array($nodeType)) {
                    foreach ($nodeType as $oneType) {
                        if (is_string($oneType) && strcasecmp($oneType, $type) === 0) {
                            return $decoded;
                        }
                    }
                }
            }
        }

        return null;
    }

    // =========================================================================
    // savePreview + saveCache
    // =========================================================================

    private function savePreview(
        GiftListItemLink $link,
        string $url,
        ?string $finalUrl,
        array $result,
        int $ttlSeconds
    ): void {
        $this->saveCache($link, $result, $ttlSeconds);
    }

    private function saveCache(GiftListItemLink $link, array $result, int $ttlSeconds): void
    {
        $fetchedAt = new \DateTimeImmutable('now');
        $expiresAt = $fetchedAt->modify('+' . $ttlSeconds . ' seconds');

        try {
            $dto = $this->arrayToLinkPreviewDto($result);
            $link->setPreviewJson($dto);
            $link->setPreviewStatus((string) ($result['status'] ?? 'failed'));
            $link->setPreviewFetchedAt($fetchedAt);
            $link->setPreviewExpiresAt($expiresAt);
            $link->setLastUpdatedAt(new \DateTimeImmutable('now'));

            $this->entityManager->flush();
        } catch (Throwable $exception) {
            // Don't break the flow if cache save fails.
            $this->logger->error('Failed to save preview cache', [
                'giftListItemLinkId' => $link->getId(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function arrayToLinkPreviewDto(array $result): LinkPreview
    {
        $dto = new LinkPreview();
        $dto->setUrl($result['url'] ?? null);
        $dto->setTitle($result['preview']['title'] ?? $result['title'] ?? null);
        $dto->setStatus($result['status'] ?? null);
        $dto->setProduct($result['product'] !== null ? json_encode($result['product']) : null);
        $dto->setFinalUrl($result['finalUrl'] ?? null);
        $dto->setImageUrl($result['preview']['imageUrl'] ?? $result['imageUrl'] ?? null);
        $dto->setSiteName($result['preview']['siteName'] ?? $result['siteName'] ?? null);
        $dto->setFaviconUrl($result['preview']['faviconUrl'] ?? $result['faviconUrl'] ?? null);
        $dto->setDescription($result['preview']['description'] ?? $result['description'] ?? null);

        return $dto;
    }

    private function ttlForStatus(string $status): int
    {
        if ($status === 'ok') {
            return self::CACHE_OK_TTL_SECONDS;
        }

        return self::CACHE_FAILED_TTL_SECONDS;
    }

    // =========================================================================
    // Small helpers
    // =========================================================================

    private function failResult(string $status, string $message, ?string $url = null, ?string $finalUrl = null): array
    {
        $host = $url !== null ? $this->hostFromUrl($url) : null;

        $faviconUrl = null;
        if ($host !== null) {
            $scheme = 'https';
            $schemeFromUrl = $url !== null ? parse_url($url, PHP_URL_SCHEME) : null;
            if (is_string($schemeFromUrl) && $schemeFromUrl !== '') {
                $scheme = $schemeFromUrl;
            }

            $faviconUrl = $scheme . '://' . $host . '/favicon.ico';
        }

        return [
            'status' => $status,
            'url' => $url,
            'finalUrl' => $finalUrl,
            'title' => $host,
            'description' => null,
            'imageUrl' => null,
            'siteName' => $host,
            'faviconUrl' => $faviconUrl,
            'product' => null,
            'warnings' => [$message],
        ];
    }

    private function firstMetaContent(DOMXPath $xpath, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            [$attribute, $value] = $candidate;

            $query = sprintf('//meta[@%s="%s"]/@content', $attribute, $this->escapeXpathLiteral($value));
            $nodes = $xpath->query($query);

            if ($nodes !== false && $nodes->length > 0) {
                $content = trim((string) $nodes->item(0)?->nodeValue);
                if ($content !== '') {
                    return $content;
                }
            }
        }

        return null;
    }

    private function titleTag(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//title');
        if ($nodes !== false && $nodes->length > 0) {
            $title = trim((string) $nodes->item(0)?->textContent);
            return $title !== '' ? $title : null;
        }

        return null;
    }

    private function firstLinkHref(DOMXPath $xpath, array $relValues): ?string
    {
        foreach ($relValues as $relValue) {
            $query = sprintf('//link[translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="%s"]/@href', $this->escapeXpathLiteral(strtolower($relValue)));
            $nodes = $xpath->query($query);

            if ($nodes !== false && $nodes->length > 0) {
                $href = trim((string) $nodes->item(0)?->nodeValue);
                if ($href !== '') {
                    return $href;
                }
            }
        }

        return null;
    }

    private function normalizeMaybeRelativeUrl(string $baseUrl, ?string $maybeUrl): ?string
    {
        if ($maybeUrl === null) {
            return null;
        }

        $maybeUrl = trim($maybeUrl);
        if ($maybeUrl === '') {
            return null;
        }

        if (str_starts_with($maybeUrl, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
            if (!is_string($scheme) || $scheme === '') {
                $scheme = 'https';
            }
            return $scheme . ':' . $maybeUrl;
        }

        if (filter_var($maybeUrl, FILTER_VALIDATE_URL)) {
            return $maybeUrl;
        }

        // Relative -> build absolute (simple)
        $baseParts = parse_url($baseUrl);
        if (!is_array($baseParts)) {
            return null;
        }

        $scheme = (string) ($baseParts['scheme'] ?? 'https');
        $host = (string) ($baseParts['host'] ?? '');
        if ($host === '') {
            return null;
        }

        $prefix = $scheme . '://' . $host;

        if (!str_starts_with($maybeUrl, '/')) {
            $maybeUrl = '/' . $maybeUrl;
        }

        return $prefix . $maybeUrl;
    }

    private function hostFromUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        return is_string($host) && $host ? $host : null;
    }

    private function firstHeaderValue(array $headers, string $headerName): ?string
    {
        $headerNameLower = strtolower($headerName);

        foreach ($headers as $name => $values) {
            if (strtolower((string) $name) === $headerNameLower && is_array($values) && isset($values[0]) && is_string($values[0])) {
                return $values[0];
            }
        }

        return null;
    }

    private function finalUrlFromResponse(mixed $response, string $fallbackUrl): string
    {
        // Symfony HttpClient doesn't expose the final URL cleanly everywhere.
        // Best-effort: check response info.
        try {
            if (method_exists($response, 'getInfo')) {
                $url = $response->getInfo('url');
                if (is_string($url) && $url) {
                    return $url;
                }
            }
        } catch (Throwable) {
            // ignore
        }

        return $fallbackUrl;
    }

    private function escapeXpathLiteral(string $value): string
    {
        // simple literal escape for XPath
        if (!str_contains($value, '"')) {
            return $value;
        }

        // fallback: strip quotes (good enough for our fixed strings)
        return str_replace('"', '', $value);
    }
}
