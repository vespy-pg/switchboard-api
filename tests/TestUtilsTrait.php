<?php

namespace App\Tests;

use App\DataFixtures\FixtureSetup;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

trait TestUtilsTrait
{
    /**
     * @var Loader
     */
    protected $fixtureLoader;
    /**
     * @var ORMExecutor
     */
    protected $fixtureExecutor;
    private $loadedAllFixtures = [];

    protected function loadFixture(
        string $class,
        array $params = [],
        callable $callback = null
    ): string {
        if (!static::$kernel) {
            static::bootKernel();
        }
        $setup = new FixtureSetup();
        $setup->addMethod('loadOne', $params ?: [], $callback);
        $this->getFixtureLoader(true)->addFixture(new $class($setup));
        $this->getFixtureExecutor()->execute($this->getFixtureLoader()->getFixtures(), true);
        $fixtures = $this->getFixtureLoader()->getFixtures();
        $fixture = array_shift($fixtures)->getLoadedFixture();
        if (method_exists($fixture, 'getId')) {
            $pk = $fixture->getId();
        } elseif (method_exists($fixture, 'getCode')) {
            $pk = $fixture->getCode();
        } else {
            throw new \Exception('Fixture does not have getId or getCode method');
        }
        return $pk;
    }

    private function getFixtureLoader($forceNew = false): Loader
    {
        if ($forceNew || !$this->fixtureLoader) {
            $this->fixtureLoader = new Loader();
        }
        return $this->fixtureLoader;
    }

    private function getFixtureExecutor($forceNew = false): ORMExecutor
    {
        if ($forceNew || !$this->fixtureExecutor) {
            $purger = new ORMPurger();
            $this->fixtureExecutor = new ORMExecutor($this->getEM(), $purger);
            $this->loadedAllFixtures = [];
        }
        return $this->fixtureExecutor;
    }

    protected function getEM(): EntityManagerInterface
    {
        return static::$kernel->getContainer()->get('doctrine.orm.entity_manager');
    }

    protected function uuidV4(): string
    {
        $setup = new FixtureSetup();
        return $setup->uuidV4();
    }

    protected function randInteger(): int
    {
        return mt_rand($min = 100000000, $min * 10 - 1);
    }

    protected function randDate(): string
    {
        return rand((int)date('Y') - 100, (int)date('Y') - 20) . '-' .
            str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' .
            str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
    }

    protected function randDateTime(): string
    {
        return $this->randDate() . 'T' . str_pad(rand(0, 23), 2, '0', STR_PAD_LEFT) . ':' .
            str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT) . ':' .
            str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT);
    }

    protected function arraySoftMerge(array $array, array ...$moreArrays): array
    {
        foreach ($moreArrays as $moreArray) {
            foreach ($moreArray as $key => $value) {
                if (is_array($value)) {
                    $array[$key] = $this->arraySoftMerge($array[$key] ?? [], $value);
                } else {
                    $array[$key] = $value;
                }
            }
        }
        return $array;
    }
}
