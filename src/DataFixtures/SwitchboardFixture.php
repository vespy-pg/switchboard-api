<?php

namespace App\DataFixtures;

use App\Entity\Switchboard;
use App\Entity\Project;
use DateTimeImmutable;

class SwitchboardFixture extends AbstractFixture
{
    public function loadOne(): void
    {
        $entity = new Switchboard();
        $rand = $this->uuidV4();

        $entity->setProject($this->getEM()->getRepository(Project::class)->find(FixtureSetup::DEFAULT_PROJECT_ID));
        $entity->setName('sample_' . $rand);
        $entity->setContentJson([]);
        $entity->setVersion($this->uuidV4());
        $entity->setCreatedAt(new DateTimeImmutable());
        $this->persist($entity);
    }
}
