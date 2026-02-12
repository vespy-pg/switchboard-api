<?php

namespace App\DataFixtures;

use App\Entity\Project;
use App\Entity\User;

class ProjectFixture extends AbstractFixture
{
    public function loadOne(): void
    {
        $entity = new Project();
        $rand = $this->randId();

        $entity->setUser($this->getEM()->getRepository(User::class)->find(FixtureSetup::DEFAULT_USER_ID));
        $entity->setName('sample_' . $rand);
        $entity->setCreatedAt(new DateTime());
        $this->persist($entity);
    }
}
