<?php

namespace App\Tests\Web;

use App\DataFixtures\UserFixture;
use App\Tests\Web\AbstractWebTestCase;
use Exception;
use Symfony\Component\HttpFoundation\Response;

class UserWebTest extends AbstractWebTestCase
{
    public function testListUsers(): void
    {
        $client = static::createClient();
        $userId1 = $this->loadFixture(UserFixture::class, ['dupa jasia']);
        $userId2 = $this->loadFixture(UserFixture::class);
        $client->request('GET', '/api/users', ['id' => [$userId1, $userId2]]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseFormatSame('jsonld');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('member', $data);
        $this->assertIsArray($data['member']);
        $this->assertEquals(2, count($data['member']));
        foreach ($data['member'] as $user) {
            if (!in_array($user['id'], [$userId1, $userId2])) {
                throw new Exception('Invalid user returned by listUsers endpoint');
            }
        }
    }

    public function testViewUser(): void
    {
        $client = static::createClient();
        $userId = $this->loadFixture(UserFixture::class);
        $client->request('GET', "/api/users/$userId");

        $this->assertResponseIsSuccessful();
        $this->assertResponseFormatSame('jsonld');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals($userId, $data['id']);
    }

    public function testCreateUser(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/users', [], [], ['CONTENT_TYPE' => 'application/ld+json'], json_encode([
            'name' => 'Test User',
            'isActive' => true,
            'email' => $this->uuidV4() . '@example.com',
            'password' => 'securepassword'
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('Test User', $data['name']);
    }

    public function testUpdateUser(): void
    {
        $client = static::createClient();
        $userId = $this->loadFixture(UserFixture::class);
        $client->request('PATCH', "/api/users/$userId", [], [], ['CONTENT_TYPE' => 'application/merge-patch+json'], json_encode([
            'name' => 'Updated Test User'
        ]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Updated Test User', $data['name']);
    }

    public function testDeleteUser(): void
    {
        $client = static::createClient();
        $userId = $this->loadFixture(UserFixture::class);
        $client->request('DELETE', "/api/users/$userId");

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }
}
