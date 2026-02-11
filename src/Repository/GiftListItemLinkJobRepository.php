<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final class GiftListItemLinkJobRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Claim a batch of links for processing and return their IDs.
     * - Only returns links that need refresh (missing/expired preview)
     * - Skips removed rows
     * - Skips rows currently locked by another worker
     */
    public function claimPreviewBatch(int $limit, string $lockToken, int $lockSeconds): array
    {
        $this->connection->beginTransaction();

        try {
            $ids = $this->connection->fetchFirstColumn(<<<'SQL'
SELECT gift_list_item_link_id
FROM app.tbl_gift_list_item_link
WHERE removed_at IS NULL
  AND (
        preview_expires_at IS NULL
        OR preview_expires_at <= NOW()
      )
  AND (
        preview_processing_locked_until IS NULL
        OR preview_processing_locked_until <= NOW()
      )
ORDER BY COALESCE(preview_fetched_at, created_at) ASC
LIMIT :limit
FOR UPDATE SKIP LOCKED
SQL, [
                'limit' => $limit,
            ]);

            if ($ids === []) {
                $this->connection->commit();
                return [];
            }

            $this->connection->executeStatement(
                <<<SQL
UPDATE app.tbl_gift_list_item_link
SET preview_processing_lock_token = :lockToken,
    preview_processing_locked_until = NOW() + (:lockSeconds || ' seconds')::interval
WHERE gift_list_item_link_id = ANY(:ids)
SQL,
                [
                    'lockToken' => $lockToken,
                    'lockSeconds' => (string) $lockSeconds,
                    'ids' => $ids,
                ],
                [
                    'ids' => ArrayParameterType::STRING,
                ]
            );

            $this->connection->commit();

            return $ids;
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    public function releasePreviewLock(string $giftListItemLinkId, string $lockToken): void
    {
        $this->connection->executeStatement(<<<'SQL'
UPDATE app.tbl_gift_list_item_link
SET preview_processing_lock_token = NULL,
    preview_processing_locked_until = NULL
WHERE gift_list_item_link_id = :id
  AND preview_processing_lock_token = :lockToken
SQL, [
            'id' => $giftListItemLinkId,
            'lockToken' => $lockToken,
        ]);
    }
}
