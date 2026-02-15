<?php

namespace App\State\Project;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Project;
use App\Entity\Switchboard;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ProjectCreateProcessor implements ProcessorInterface
{
    private const MAX_ACTIVE_PROJECTS = 20;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Project) {
            return $data;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \RuntimeException('User must be authenticated to create a project');
        }

        $activeProjectsCount = (int) $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(project.id)')
            ->from(Project::class, 'project')
            ->where('project.user = :user')
            ->andWhere('project.removedAt IS NULL')
            ->andWhere('project.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        if ($activeProjectsCount >= self::MAX_ACTIVE_PROJECTS) {
            throw new BadRequestHttpException(
                sprintf('Maximum number of active projects reached (%d). Archive a project to create a new one.', self::MAX_ACTIVE_PROJECTS)
            );
        }

        $data->setCreatedAt(new DateTimeImmutable());
        $data->setUser($user);
        if (!$data->getName()) {
            $data->setName('New project');
        }

        $switchboard = new Switchboard();
        $switchboard->setName('New switchboard');
        $data->addSwitchboard($switchboard);
        $switchboard->setVersion(1);
        $switchboard->setCreatedAt(new DateTimeImmutable());

        $this->entityManager->persist($data);
        $this->entityManager->persist($switchboard);
        $this->entityManager->flush();

        return $data;
    }
}
