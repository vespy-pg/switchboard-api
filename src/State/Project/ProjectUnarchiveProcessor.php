<?php

namespace App\State\Project;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Project;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ProjectUnarchiveProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Project) {
            return $data;
        }

        if ($data->getRemovedAt() !== null) {
            throw new BadRequestHttpException('Removed project cannot be unarchived.');
        }

        if ($data->getArchivedAt() === null) {
            throw new BadRequestHttpException('Project is not archived.');
        }

        $data->setArchivedAt(null);
        $data->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return $data;
    }
}
