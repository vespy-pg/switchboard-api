<?php

namespace App\DataFixtures;

class FixtureSetup
{
    public const DEFAULT_BASIC_USER_ID = '703d21d8-ef98-40a3-b363-8c829fbaa423';
    public const DEFAULT_VERIFIED_USER_ID = '703d21d8-ef98-40a3-b363-8c829fbaa424';

    /**
     * Class constructor.
     *
     * @param array $methods Array containing method with it's params.
     *    $methods = [
     *          [
     *              'name'      => (string) DB hostname. Required.
     *              'params'    => (array)
     *              'callback'    => (function(entity))
     *          }
     *    ]
     */
    private $methods = [];
    private $loadedFixtures = [];

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function addMethod(string $methodName, array $params = [], $callback = null)
    {
        $this->methods[] = ['name' => $methodName, 'params' => $params, 'callback' => $callback];
    }

    public function addLoadedFixture($fixture)
    {
        $this->loadedFixtures[] = $fixture;
    }

    public function getLoadedFixtures()
    {
        return $this->loadedFixtures;
    }

    public function uuidV4()
    {
        $data = random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function randId(?int $maxLength = null): string
    {
        $time = explode(' ', microtime());
        $rand = substr(($time[1] . str_replace('0.', '', $time[0])), 0, -2) . mt_rand($min = 10000, $min * 10 - 1);
        return $maxLength ? substr($rand, 0, min($maxLength, strlen($rand))) : $rand;
    }
}
