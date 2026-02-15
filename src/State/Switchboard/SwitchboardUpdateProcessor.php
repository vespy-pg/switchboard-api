<?php

namespace App\State\Switchboard;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Switchboard;
use App\Service\ProjectArchiveGuard;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SwitchboardUpdateProcessor implements ProcessorInterface
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

        if ($data->getRemovedAt() !== null) {
            throw new BadRequestHttpException('Removed switchboard cannot be updated.');
        }

        $this->projectArchiveGuard->assertSwitchboardWritable($data);

        $data->setUpdatedAt(new DateTimeImmutable());
        $data->setVersion($data->getVersion() + 1);

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
