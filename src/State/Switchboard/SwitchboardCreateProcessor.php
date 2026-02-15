<?php

namespace App\State\Switchboard;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Switchboard;
use App\Service\ProjectArchiveGuard;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class SwitchboardCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectArchiveGuard $projectArchiveGuard,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Switchboard) {
            return $data;
        }

        if (!$data->getName()) {
            $data->setName('New switchboard');
        }

        $project = $data->getProject();
        if ($project !== null) {
            $this->projectArchiveGuard->assertProjectWritable($project);
        }

        $data->setVersion(1);
        $data->setCreatedAt(new DateTimeImmutable());

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
