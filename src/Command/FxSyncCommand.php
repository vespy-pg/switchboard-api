<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Currency;
use App\Entity\FxRate;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use SimpleXMLElement;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:fx:sync',
    description: 'Sync FX rates from external providers (ECB for now)'
)]
final class FxSyncCommand extends Command
{
    /**
     * Minimal “credentials/config” container so you can add more sources later without refactoring.
     * ECB needs no credentials, but the shape is here.
     */
    private const SOURCES = [
        'ecb' => [
            'url' => 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml',
            'credentials' => [],
            'baseCurrencyCode' => 'EUR',
        ],
    ];

    private ObjectRepository $currencyRepository;
    private ObjectRepository $fxRateRepository;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();

        $this->currencyRepository = $this->entityManager->getRepository(Currency::class);
        $this->fxRateRepository = $this->entityManager->getRepository(FxRate::class);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::OPTIONAL, 'Rate source key (ecb)', 'ecb');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sourceKey = (string) $input->getArgument('source');

        if (!isset(self::SOURCES[$sourceKey])) {
            $output->writeln(sprintf('Unknown source "%s". Allowed: %s', $sourceKey, implode(', ', array_keys(self::SOURCES))));
            return Command::INVALID;
        }

        $sourceConfig = self::SOURCES[$sourceKey];

        try {
            $fxRows = $this->fetchEcbRates($sourceConfig['url'], (string) $sourceConfig['baseCurrencyCode']);
        } catch (\Throwable $exception) {
            $output->writeln('<error>Failed to fetch or parse FX rates: ' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $persistedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;

        $baseCurrency = $this->findCurrencyOrNull($fxRows['baseCurrencyCode']);
        if ($baseCurrency === null) {
            $output->writeln(sprintf('<error>Base currency "%s" not found in tbl_currency.</error>', $fxRows['baseCurrencyCode']));
            return Command::FAILURE;
        }

        foreach ($fxRows['rates'] as $toCurrencyCode => $rateValue) {
            $toCurrency = $this->findCurrencyOrNull($toCurrencyCode);
            if ($toCurrency === null) {
                $skippedCount++;
                $output->writeln(sprintf('<comment>Skipping currency "%s" because it is not present in tbl_currency.</comment>', $toCurrencyCode));
                continue;
            }

            $existingFxRate = $this->fxRateRepository->findOneBy([
                'rateDate' => $fxRows['rateDate'],
                'fromCurrency' => $baseCurrency,
                'toCurrency' => $toCurrency,
            ]);

            if ($existingFxRate instanceof FxRate) {
                $existingFxRate->setRate($rateValue);
                $existingFxRate->setFetchedAt($fxRows['fetchedAt']);
                $updatedCount++;
                continue;
            }

            $newFxRate = new FxRate();
            $newFxRate->setFromCurrency($baseCurrency);
            $newFxRate->setToCurrency($toCurrency);
            $newFxRate->setRate($rateValue);
            $newFxRate->setRateDate($fxRows['rateDate']);
            $newFxRate->setFetchedAt($fxRows['fetchedAt']);

            $this->entityManager->persist($newFxRate);
            $persistedCount++;
        }

        $this->entityManager->flush();

        $output->writeln(sprintf(
            'FX sync done. inserted=%d updated=%d skipped=%d date=%s fetchedAt=%s',
            $persistedCount,
            $updatedCount,
            $skippedCount,
            $fxRows['rateDate']->format('Y-m-d'),
            $fxRows['fetchedAt']->format(DATE_ATOM)
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array{
     *   baseCurrencyCode: string,
     *   rateDate: DateTimeImmutable,
     *   fetchedAt: DateTimeImmutable,
     *   rates: array<string, string>
     * }
     */
    private function fetchEcbRates(string $url, string $baseCurrencyCode): array
    {
        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'Accept' => 'application/xml,text/xml,*/*',
            ],
        ]);

        $xmlContent = $response->getContent();

        $xml = new SimpleXMLElement($xmlContent);

        // ECB structure: Envelope > Cube > Cube(time=YYYY-MM-DD) > Cube(currency=XXX rate=Y)
        $cubeNodes = $xml->xpath('//*[local-name()="Cube"]');
        if (!is_array($cubeNodes) || count($cubeNodes) === 0) {
            throw new \RuntimeException('ECB XML does not contain expected Cube nodes.');
        }

        $timeNode = null;
        foreach ($cubeNodes as $cubeNode) {
            $attributes = $cubeNode->attributes();
            if ($attributes !== null && isset($attributes['time'])) {
                $timeNode = $cubeNode;
                break;
            }
        }

        if (!$timeNode instanceof SimpleXMLElement) {
            throw new \RuntimeException('ECB XML does not contain a Cube node with a time attribute.');
        }

        $rateDateString = (string) $timeNode->attributes()?->time;
        if ($rateDateString === '') {
            throw new \RuntimeException('ECB XML time attribute is empty.');
        }

        $rateDate = new DateTimeImmutable($rateDateString);
        $fetchedAt = new DateTimeImmutable('now');

        $rates = [];

        foreach ($timeNode->children() as $childNode) {
            $attributes = $childNode->attributes();
            if ($attributes === null) {
                continue;
            }

            $currencyCode = (string) ($attributes['currency'] ?? '');
            $rateValue = (string) ($attributes['rate'] ?? '');

            if ($currencyCode === '' || $rateValue === '') {
                continue;
            }

            $rates[$currencyCode] = $rateValue;
        }

        if (count($rates) === 0) {
            throw new \RuntimeException('No currency rates found in ECB XML.');
        }

        return [
            'baseCurrencyCode' => $baseCurrencyCode,
            'rateDate' => $rateDate,
            'fetchedAt' => $fetchedAt,
            'rates' => $rates,
        ];
    }

    private function findCurrencyOrNull(string $currencyCode): ?Currency
    {
        $currency = $this->currencyRepository->findOneBy(['code' => $currencyCode]);

        if ($currency instanceof Currency) {
            return $currency;
        }

        return null;
    }
}
