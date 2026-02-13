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

class ProjectCreateProcessor implements ProcessorInterface
{
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
