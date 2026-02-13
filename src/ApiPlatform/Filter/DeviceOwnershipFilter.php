<?php

declare(strict_types=1);

namespace App\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;

class DeviceOwnershipFilter extends AbstractFilter
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly Security $security,
    ) {
        parent::__construct($managerRegistry);
    }

    protected function filterProperty(
        string $property,
        $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        if ($property !== 'ownership' || !$value) {
            return;
        }

        if (!in_array($value, ['predefined', 'mine', 'public'], true)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        if ($value === 'predefined') {
            $queryBuilder->andWhere(sprintf('%s.ownerUser IS NULL', $alias));

            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $parameterName = $queryNameGenerator->generateParameterName('ownershipUser');
        if ($value === 'mine') {
            $queryBuilder
                ->andWhere(sprintf('%s.ownerUser = :%s', $alias, $parameterName))
                ->setParameter($parameterName, $user);

            return;
        }

        $queryBuilder
            ->andWhere(sprintf('%s.ownerUser IS NOT NULL', $alias))
            ->andWhere(sprintf('%s.ownerUser != :%s', $alias, $parameterName))
            ->setParameter($parameterName, $user);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'ownership' => [
                'property' => null,
                'type' => 'string',
                'required' => false,
                'description' => 'Filter devices by ownership: predefined (no owner), mine (owned by current user), public (owned by someone else).',
            ],
        ];
    }
}
