<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\GiftListItemLinkJobRepository;
use App\Service\GiftListItemLinkPreviewService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:gift-list-item-link:process',
    description: 'Process link previews in batches (background job)'
)]
final class GiftListItemLinkProcessCommand extends Command
{
    public function __construct(
        private readonly GiftListItemLinkJobRepository $jobRepository,
        private readonly GiftListItemLinkPreviewService $previewService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Batch size', '20')
            ->addOption('lock-seconds', null, InputOption::VALUE_REQUIRED, 'Lock duration in seconds', '300');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = (int) $input->getOption('limit');
        $lockSeconds = (int) $input->getOption('lock-seconds');

        if ($limit <= 0) {
            $io->error('Limit must be > 0');
            return Command::FAILURE;
        }

        $lockToken = $this->generateUuidV4();

        $ids = $this->jobRepository->claimPreviewBatch($limit, $lockToken, $lockSeconds);

        if ($ids === []) {
            $io->success('Nothing to process');
            return Command::SUCCESS;
        }

        $io->text('Claimed ' . count($ids) . ' link(s)');

        $processedCount = 0;
        $blockedCount = 0;
        $failedCount = 0;

        foreach ($ids as $giftListItemLinkId) {
            try {
                $result = $this->previewService->forceRefetchPreviewForLinkId((string) $giftListItemLinkId);

                $status = (string) ($result['status'] ?? 'failed');

                if ($status === 'ok') {
                    $processedCount++;
                } elseif ($status === 'blocked') {
                    $blockedCount++;
                } else {
                    $failedCount++;
                }

                // TODO (later): domain safety lookup hook goes here
                // $this->domainSafetyService->getOrRefreshDomainStatus(...)

            } catch (\Throwable $exception) {
                $failedCount++;
                // Keep going, don’t crash the batch.
            } finally {
                $this->jobRepository->releasePreviewLock((string) $giftListItemLinkId, $lockToken);
            }
        }

        $io->success(sprintf(
            'Done. ok=%d blocked=%d failed=%d',
            $processedCount,
            $blockedCount,
            $failedCount
        ));

        return Command::SUCCESS;
    }

    private function generateUuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
