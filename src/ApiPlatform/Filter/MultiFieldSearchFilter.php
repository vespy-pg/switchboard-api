<?php

namespace App\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\FilterInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\Mapping\Column;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class MultiFieldSearchFilter extends AbstractFilter implements FilterInterface
{
    private SearchFilter $searchFilter;
    private ReflectionClass $reflectionClass;
    private string $resourceClass;

    public function __construct(
        ManagerRegistry $managerRegistry,
        ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        ?LoggerInterface $logger = null,
        ?array $properties = null,
        ?NameConverterInterface $nameConverter = null,
    ) {
        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
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
        if ($property !== 'searchPhrase' || !$value || !$this->getProperties()) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $orX = $queryBuilder->expr()->orX();
        $this->reflectionClass = new ReflectionClass($resourceClass);
        $this->resourceClass = $resourceClass;
        foreach ($this->getProperties() as $column => $searchType) {
            $paramName = "searchPhrase_$searchType";
            $columnType = $this->getColumnType($column);
            $paramBindName = $this->getParamNameCasted($column, $paramName);
            $columnAlias = "$alias.$column";
            if ($columnType === 'relation') {
                $columnAlias = 'IDENTITY(' . $columnAlias . ')';
            }
            $value = $this->formatValue($value, $columnType);
            switch ($searchType) {
                case 'exact':
                    $orX->add($queryBuilder->expr()->eq("LOWER(CONCAT($columnAlias, ''))", "LOWER($paramBindName)"));
                    $queryBuilder->setParameter($paramName, $value);
                    break;
                case 'partial':
                    $orX->add($queryBuilder->expr()->like("LOWER(CONCAT($columnAlias, ''))", "LOWER($paramBindName)"));
                    $queryBuilder->setParameter($paramName, "%$value%");
                    break;
                case 'start':
                    $orX->add($queryBuilder->expr()->like("LOWER(CONCAT($columnAlias, ''))", "LOWER($paramBindName)"));
                    $queryBuilder->setParameter($paramName, "$value%");
                    break;
                case 'end':
                    $orX->add($queryBuilder->expr()->like("LOWER(CONCAT($columnAlias, ''))", "LOWER($paramBindName)"));
                    $queryBuilder->setParameter($paramName, "%$value");
                    break;
                default:
                    throw new InvalidArgumentException("Unsupported filter type: $searchType");
            }
        }
        $queryBuilder->andWhere($orX);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'searchPhrase' => [
                'property' => null,
                'type' => 'string',
                'required' => false,
                'description' => 'Search across multiple fields.',
            ],
        ];
    }

    private function getParamNameCasted(string $columnType, string $paramName): string
    {
        $paramName = ':' . $paramName;
        switch ($columnType) {
            default:
                return "CONCAT($paramName, '')";
        }
    }

    private function getColumnType(string $propertyName): ?string
    {
        try {
            if (!$this->reflectionClass->hasProperty($propertyName)) {
                return null;
            }
            $property = new ReflectionProperty($this->resourceClass, $propertyName);

            $attributes = $property->getAttributes(Column::class);
            if ($attributes) {
                $column = $attributes[0]->newInstance();
                return $column->type ?? 'string';
            }
            $attributes = $property->getAttributes(ManyToOne::class);
            if ($attributes) {
                return 'relation';
            }
            return null;
        } catch (ReflectionException $e) {
            return null;
        }
    }

    private function formatValue(string $value, string $columnType): string
    {
        switch ($columnType) {
            case 'datetime':
                return preg_replace('/\//', '-', $value);
            default:
                return $value;
        }
    }
}
