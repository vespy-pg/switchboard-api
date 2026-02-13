<?php

declare(strict_types=1);

namespace App\Doctrine\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class RemovedAtSoftDeleteFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        if (!$targetEntity->hasField('removedAt')) {
            return '';
        }

        $removedAtColumnName = $targetEntity->getColumnName('removedAt');

        return sprintf('%s.%s IS NULL', $targetTableAlias, $removedAtColumnName);
    }
}
