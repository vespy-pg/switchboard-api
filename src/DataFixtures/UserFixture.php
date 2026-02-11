<?php

namespace App\DataFixtures;

use App\Entity\User;
use DateTimeImmutable;

class UserFixture extends AbstractFixture
{
    public function loadOne(): void
    {
        $entity = new User();
        $uniqid = uniqid();
        $entity->setCreatedAt(new DateTimeImmutable());
        $entity->setEmail('test_' . $uniqid . '@test.com');
        $this->persist($entity);
    }
}
