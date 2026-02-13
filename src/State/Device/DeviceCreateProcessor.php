<?php

namespace App\State\Device;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Device;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class DeviceCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Device) {
            return $data;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \RuntimeException('User must be authenticated to create a device');
        }

        $data->setCreatedAt(new DateTimeImmutable());
        $data->setOwnerUser($user);
        $data->setConfigJson(['a' => 'b']);

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
