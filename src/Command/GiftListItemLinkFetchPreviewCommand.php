<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\GiftListItemLinkPreviewService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:gift-list-item-link:fetch-preview',
    description: 'Fetch or return cached preview for a gift list item link'
)]
final class GiftListItemLinkFetchPreviewCommand extends Command
{
    public function __construct(
        private readonly GiftListItemLinkPreviewService $previewService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'giftListItemLinkId',
            InputArgument::REQUIRED,
            'UUID of app.tbl_gift_list_item_link'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $giftListItemLinkId = (string) $input->getArgument('giftListItemLinkId');

        $io->text('Fetching preview…');
        $io->text('Link ID: ' . $giftListItemLinkId);

        $result = $this->previewService->getOrFetchPreviewForLinkId($giftListItemLinkId);

        $status = (string) ($result['status'] ?? 'unknown');

        if ($status !== 'ok') {
            $io->warning('Preview fetch finished with status: ' . $status);

            if (!empty($result['warnings']) && is_array($result['warnings'])) {
                foreach ($result['warnings'] as $warning) {
                    $io->text('• ' . $warning);
                }
            }

            $io->newLine();
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return Command::FAILURE;
        }

        $io->success('Preview fetched successfully');
        $io->newLine();
        $io->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return Command::SUCCESS;
    }
}
