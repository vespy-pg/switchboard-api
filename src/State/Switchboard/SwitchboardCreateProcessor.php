<?php

namespace App\State\Switchboard;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Switchboard;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class SwitchboardCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
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

        $data->setVersion(1);
        $data->setCreatedAt(new DateTimeImmutable());
        $data->setContentJson([]);

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
