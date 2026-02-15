<?php

namespace App\State\Project;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Project;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ProjectArchiveProcessor implements ProcessorInterface
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
            throw new BadRequestHttpException('Removed project cannot be archived.');
        }

        if ($data->getArchivedAt() !== null) {
            throw new BadRequestHttpException('Project is already archived.');
        }

        $data->setArchivedAt(new DateTimeImmutable());
        $data->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return $data;
    }
}
