<?php

namespace App\DataProvider;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use ApiPlatform\Doctrine\Orm\Paginator;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

class UserDataProvider implements ProviderInterface
{
    private iterable $collectionExtensions;

    public function __construct(
        private ManagerRegistry $managerRegistry,
        iterable $collectionExtensions,
        private RequestStack $requestStack,
        private readonly ClassMetadataFactoryInterface $classMetadataFactory,
    ) {
        $this->collectionExtensions = $collectionExtensions;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $entityClass = User::class;
        $repository = $this->managerRegistry->getRepository($entityClass);
        $queryNameGenerator = new QueryNameGenerator();
        $queryBuilder = $repository->createQueryBuilder('u');
        $organizationId = $this->requestStack->getCurrentRequest()->get('organizationId');
        $teamId = $this->requestStack->getCurrentRequest()->get('team');
        if ($operation instanceof GetCollection) {
            $queryBuilder
                ->leftJoin('u.userOrganizations', 'uo')
                ->leftJoin('uo.team', 't')
                ->addSelect('uo', 't')
                ->andWhere('uo.organization = :organizationId')
                ->setParameter(':organizationId', $organizationId);
            if ($teamId && $this->hasTeamSearchFilter()) {
                $queryBuilder->andWhere('uo.team = :teamId');
                $queryBuilder->setParameter(':teamId', $teamId);
            }

            foreach ($this->collectionExtensions as $extension) {
                if ($extension instanceof QueryCollectionExtensionInterface) {
                    $extension->applyToCollection(
                        $queryBuilder,
                        $queryNameGenerator,
                        $entityClass,
                        $operation,
                        $context
                    );
                }
            }
            $query = $queryBuilder->getQuery();
            $doctrinePaginator = new DoctrinePaginator($query);
            return new Paginator($doctrinePaginator);
        }

        $queryBuilder->andWhere('u.id = :id');
        $queryBuilder->setParameter('id', $uriVariables['id'] ?? null);

        return $queryBuilder->getQuery()->getSingleResult();
    }

    public function hasTeamSearchFilter(): bool
    {
        $classMetadata = $this->classMetadataFactory->getMetadataFor(User::class);
        foreach ($classMetadata->getReflectionClass()->getAttributes() as $attribute) {
            if (($attribute->getArguments()[0] ?? null) !== SearchFilter::class) {
                continue;
            }
            return $attribute->getArguments()['properties']['team'] ?? false;
        }
        return false;
    }
}
