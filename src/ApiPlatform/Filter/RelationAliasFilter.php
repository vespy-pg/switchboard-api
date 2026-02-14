<?php

namespace App\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use ReflectionClass;

class RelationAliasFilter extends AbstractFilter
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        ?array $properties = null,
    ) {
        parent::__construct($managerRegistry, null, $properties);
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
        if ($value === null || $value === '') {
            return;
        }

        $entityManager = $this->managerRegistry->getManagerForClass($resourceClass);
        if (!$entityManager instanceof EntityManagerInterface) {
            return;
        }

        $classMetadata = $entityManager->getClassMetadata($resourceClass);
        $rootAlias = $queryBuilder->getRootAliases()[0];

        foreach ($this->getAliasesByProperty($entityManager, $resourceClass) as $alias => $associationName) {
            if ($property !== $alias) {
                continue;
            }

            $parameterName = $queryNameGenerator->generateParameterName($property);
            if (is_array($value)) {
                $queryBuilder
                    ->andWhere(sprintf('IDENTITY(%s.%s) IN (:%s)', $rootAlias, $associationName, $parameterName))
                    ->setParameter($parameterName, $value);

                return;
            }

            $queryBuilder
                ->andWhere(sprintf('IDENTITY(%s.%s) = :%s', $rootAlias, $associationName, $parameterName))
                ->setParameter($parameterName, $value);

            return;
        }
    }

    public function getDescription(string $resourceClass): array
    {
        $entityManager = $this->managerRegistry->getManagerForClass($resourceClass);
        if (!$entityManager instanceof EntityManagerInterface) {
            return [];
        }

        $description = [];
        foreach (array_keys($this->getAliasesByProperty($entityManager, $resourceClass)) as $property) {
            $description[$property] = [
                'property' => null,
                'type' => 'string',
                'required' => false,
                'description' => sprintf('Filter by relation alias "%s".', $property),
            ];
        }

        return $description;
    }

    /**
     * @return array<string, string>
     */
    private function getAliasesByProperty(EntityManagerInterface $entityManager, string $resourceClass): array
    {
        $classMetadata = $entityManager->getClassMetadata($resourceClass);
        $aliasesByProperty = [];

        foreach ($classMetadata->associationMappings as $associationName => $mapping) {
            if (!$classMetadata->isSingleValuedAssociation($associationName)) {
                continue;
            }

            $targetEntityClass = $mapping['targetEntity'] ?? null;
            if (!is_string($targetEntityClass)) {
                continue;
            }

            $targetMetadata = $entityManager->getClassMetadata($targetEntityClass);
            $identifierFields = $targetMetadata->getIdentifierFieldNames();
            if (count($identifierFields) !== 1) {
                continue;
            }

            $identifierField = $identifierFields[0];
            $targetShortName = lcfirst((new ReflectionClass($targetEntityClass))->getShortName());

            $aliasesByProperty[$associationName . ucfirst($identifierField)] = $associationName;
            $aliasesByProperty[$targetShortName . ucfirst($identifierField)] = $associationName;
        }

        if (!is_array($this->properties)) {
            return $aliasesByProperty;
        }

        return array_intersect_key($aliasesByProperty, $this->properties);
    }
}
