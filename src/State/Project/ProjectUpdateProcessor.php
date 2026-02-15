<?php

namespace App\State\Project;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Project;
use App\Service\ProjectArchiveGuard;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ProjectUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectArchiveGuard $projectArchiveGuard,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Project) {
            return $data;
        }

        if ($data->getRemovedAt() !== null) {
            throw new BadRequestHttpException('Removed project cannot be updated.');
        }

        $this->projectArchiveGuard->assertProjectWritable($data);

        $data->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
