<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AbstractFixture extends Fixture implements FixtureInterface
{
    protected $setup;
    /**
     * @var ObjectManager
     */
    private $manager;
    protected $callback;

    public function __construct(FixtureSetup $setup)
    {
        $this->setup = $setup;
    }

    public function load(ObjectManager $manager): void
    {
        $this->manager = $manager;
        foreach ($this->setup->getMethods() as $method) {
            if (!method_exists($this, $method['name'])) {
                throw new \Exception('Fixture method {method} is not defined in {fixture} fixture file', [
                    'method' => $method['name'],
                    'fixture' => get_class($this)
                ]);
            }
            $this->callback = $method['callback'];
            $methodName = $method['name'];
            $this->$methodName(...$method['params']);
        }
        $this->getEM()->flush();
    }

    public function getLoadedFixture(): object
    {
        return $this->setup->getLoadedFixtures()[0];
    }

    protected function randBool()
    {
        return (bool)mt_rand(0, 1);
    }

    protected function exec($data)
    {
        if ($this->callback) {
            call_user_func($this->callback, $data);
        }
    }

    protected function persist($entity)
    {
        if ($this->manager->contains($entity)) {
            if (method_exists($entity, 'getId')) {
                $pk = $entity->getId();
            } elseif (method_exists($entity, 'getCode')) {
                $pk = $entity->getCode();
            } else {
                throw new \Exception('Fixture does not have getId or getCode method');
            }
            $existingEntity = $this->getEM()->getRepository(get_class($entity))->find($pk);
        }

        if ($existingEntity ?? false) {
            $this->setup->addLoadedFixture($existingEntity);
        } else {
            $this->setup->addLoadedFixture($entity);
            $this->exec($entity);
            $this->getEM()->persist($entity);
        }
    }

    protected function uuidV4(): string
    {
        return $this->setup->uuidV4();
    }

    protected function getEM()
    {
        return $this->manager;
    }
}
