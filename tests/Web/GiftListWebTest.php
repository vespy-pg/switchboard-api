<?php

namespace App\Tests\Web;

use App\DataFixtures\FixtureSetup;
use App\DataFixtures\UserFixture;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use App\Tests\Web\AbstractWebTestCase;

class GiftListWebTest extends AbstractWebTestCase
{
//
//    public function testCreateListWithoutAuthentication(): void
//    {
//        static::bootKernel();
//        $client = static::createClient();
//
//
//        $client->request('POST', '/api/gift-lists', [], [], [
//            'CONTENT_TYPE' => 'application/json'
//        ], json_encode([
//            'type' => 'wishlist',
//            'displayName' => 'Test Wishlist',
//            'shareSlug' => 'test-wishlist-' . time(),
//            'isUnlisted' => false
//        ]));
//
//        // Should return 401 Unauthorized
//        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
//    }
//
//    public function testCreateListWithoutRole(): void
//    {
//        static::bootKernel();
//        // Create a user without ROLE_LIST_CREATE
//        $userId = $this->loadFixture(UserFixture::class, ['test-no-role@example.com']);
//        $client = $this->createAuthenticatedClient($userId);
//
//        $client->request('POST', '/api/gift-lists', [], [], [
//            'CONTENT_TYPE' => 'application/json'
//        ], json_encode([
//            'type' => 'wishlist',
//            'displayName' => 'Test Wishlist',
//            'shareSlug' => 'test-wishlist-' . time(),
//            'isUnlisted' => false
//        ]));
//
//        // Should return 403 Forbidden (no ROLE_LIST_CREATE)
//        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
//
//        $data = json_decode($client->getResponse()->getContent(), true);
//        $this->assertArrayHasKey('error', $data);
//        $this->assertEquals('Forbidden', $data['error']);
//    }
//
//    public function testCreateListWithRole(): void
//    {
//        // Create a user and manually add ROLE_LIST_CREATE
//        $userId = $this->loadFixture(UserFixture::class, ['test-with-role@example.com']);
//
//        // Manually add user to BASIC group with ROLE_LIST_CREATE
//        $em = $this->getEM();
//        $conn = $em->getConnection();
//
//        // Check if BASIC group exists
//        $groupExists = $conn->executeQuery(
//            "SELECT EXISTS(SELECT 1 FROM app.\"group\" WHERE code = 'BASIC')"
//        )->fetchOne();
//
//        if (!$groupExists) {
//            $this->markTestSkipped('BASIC group does not exist in database. Please run migrations first.');
//            return;
//        }
//
//        // Check if ROLE_LIST_CREATE exists
//        $roleExists = $conn->executeQuery(
//            "SELECT EXISTS(SELECT 1 FROM app.role WHERE code = 'ROLE_LIST_CREATE')"
//        )->fetchOne();
//
//        if (!$roleExists) {
//            $this->markTestSkipped('ROLE_LIST_CREATE does not exist in database. Please run migrations first.');
//            return;
//        }
//
//        // Add user to BASIC group
//        $conn->executeStatement(
//            "INSERT INTO app.user_group (user_id, group_code, is_active) VALUES (:user_id, 'BASIC', TRUE) ON CONFLICT DO NOTHING",
//            ['user_id' => $userId]
//        );
//
//        $client = $this->createAuthenticatedClient($userId);
//
//        // Get user and check roles
//        $userRepository = static::getContainer()->get(UserRepository::class);
//        $user = $userRepository->find($userId);
//        $roles = $user->getRoles();
//
//        echo "\n=== User Roles Debug ===\n";
//        echo "User ID: $userId\n";
//        echo "Roles from getRoles(): " . json_encode($roles) . "\n";
//
//        // Load API roles directly
//        $apiRoles = $userRepository->loadApiRoles($userId);
//        echo "API Roles from DB: " . json_encode($apiRoles) . "\n";
//        echo "========================\n\n";
//
//        $client->request('POST', '/api/gift-lists', [], [], [
//            'CONTENT_TYPE' => 'application/json'
//        ], json_encode([
//            'type' => 'wishlist',
//            'displayName' => 'Test Wishlist With Role',
//            'shareSlug' => 'test-wishlist-with-role-' . time(),
//            'isUnlisted' => false
//        ]));
//
//        // If user has ROLE_LIST_CREATE, should return 201 Created
//        // If not, will return 403 Forbidden
//        $statusCode = $client->getResponse()->getStatusCode();
//        $content = $client->getResponse()->getContent();
//
//        echo "\n=== Response Debug ===\n";
//        echo "Status Code: $statusCode\n";
//        echo "Response: $content\n";
//        echo "======================\n\n";
//
//        if ($statusCode === Response::HTTP_FORBIDDEN) {
//            $data = json_decode($content, true);
//            $this->fail(
//                "Expected 201 Created but got 403 Forbidden. " .
//                "User roles: " . json_encode($roles) . ". " .
//                "API roles from DB: " . json_encode($apiRoles) . ". " .
//                "Response: " . json_encode($data)
//            );
//        }
//
//        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
//
//        $data = json_decode($content, true);
//        $this->assertArrayHasKey('id', $data);
//        $this->assertEquals('wishlist', $data['type']);
//        $this->assertEquals('Test Wishlist With Role', $data['displayName']);
//        $this->assertEquals($userId, $data['createdByUserId']);
//        $this->assertEquals($userId, $data['ownerUserId']);
//    }
//
//    public function testCreateListMissingRequiredFields(): void
//    {
//        $userId = $this->loadFixture(UserFixture::class, ['test-validation@example.com']);
//
//        // Add user to BASIC group
//        $conn = $this->getEM()->getConnection();
//        $conn->executeStatement(
//            "INSERT INTO app.user_group (user_id, group_code, is_active) VALUES (:user_id, 'BASIC', TRUE) ON CONFLICT DO NOTHING",
//            ['user_id' => $userId]
//        );
//
//        $client = $this->createAuthenticatedClient($userId);
//
//        $client->request('POST', '/api/gift-lists', [], [], [
//            'CONTENT_TYPE' => 'application/json'
//        ], json_encode([
//            'type' => 'wishlist'
//            // Missing displayName, shareSlug, isUnlisted
//        ]));
//
//        // Should return 400 Bad Request or 422 Unprocessable Entity
//        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
//    }

    public function testCreateListWithOptionalFields(): void
    {

        $client = $this->createAuthenticatedClient(['ROLE_LIST_CREATE']);

        $client->request('POST', '/api/gift-lists', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'type' => 'wedding',
            'displayName' => 'Wedding Registry',
            'shareSlug' => 'wedding-registry-' . time(),
            'isUnlisted' => false,
            'countryCode' => 'US',
            'languageCode' => 'en-us',
            'currencyCode' => 'USD',
            'metadata' => '{"theme": "rustic"}'
        ]));

        // Debug response
        $statusCode = $client->getResponse()->getStatusCode();
        $content = $client->getResponse()->getContent();

        if ($statusCode !== Response::HTTP_CREATED) {
            $this->fail("Expected 201 Created but got $statusCode. Response: $content");
        }

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('US', $data['countryCode']);
        $this->assertEquals('en-us', $data['languageCode']);
        $this->assertEquals('USD', $data['currencyCode']);
        $this->assertEquals('{"theme": "rustic"}', $data['metadata']);
    }

    public function testCreateListAsAnon(): void
    {

        $client = $this->createUnauthenticatedClient(['ROLE_LIST_CREATE']);

        $client->request('POST', '/api/gift-lists', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'type' => 'wedding',
            'displayName' => 'Wedding Registry',
            'shareSlug' => 'wedding-registry-' . time(),
            'isUnlisted' => false,
            'countryCode' => 'US',
            'languageCode' => 'en-us',
            'currencyCode' => 'USD',
            'metadata' => '{"theme": "rustic"}'
        ]));

        // Debug response
        $statusCode = $client->getResponse()->getStatusCode();
        $content = $client->getResponse()->getContent();

        if ($statusCode !== Response::HTTP_CREATED) {
            $this->fail("Expected 201 Created but got $statusCode. Response: $content");
        }

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('US', $data['countryCode']);
        $this->assertEquals('en-us', $data['languageCode']);
        $this->assertEquals('USD', $data['currencyCode']);
        $this->assertEquals('{"theme": "rustic"}', $data['metadata']);
    }

//    public function testListCreatedByUserIsSetAutomatically(): void
//    {
//        $userId = $this->loadFixture(UserFixture::class, ['test-auto-fields@example.com']);
//
//        // Add user to BASIC group
//        $conn = $this->getEM()->getConnection();
//        $conn->executeStatement(
//            "INSERT INTO app.user_group (user_id, group_code, is_active) VALUES (:user_id, 'BASIC', TRUE) ON CONFLICT DO NOTHING",
//            ['user_id' => $userId]
//        );
//
//        $client = $this->createAuthenticatedClient($userId);
//
//        $client->request('POST', '/api/gift-lists', [], [], [
//            'CONTENT_TYPE' => 'application/json'
//        ], json_encode([
//            'type' => 'birthday',
//            'displayName' => 'Birthday List',
//            'shareSlug' => 'birthday-list-' . time(),
//            'isUnlisted' => true
//        ]));
//
//        if ($client->getResponse()->getStatusCode() === Response::HTTP_FORBIDDEN) {
//            $this->markTestSkipped('User does not have ROLE_LIST_CREATE. Test skipped.');
//            return;
//        }
//
//        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
//
//        $data = json_decode($client->getResponse()->getContent(), true);
//
//        // Verify automatic fields
//        $this->assertArrayHasKey('createdByUserId', $data);
//        $this->assertArrayHasKey('ownerUserId', $data);
//        $this->assertArrayHasKey('createdAt', $data);
//
//        $this->assertEquals($userId, $data['createdByUserId']);
//        $this->assertEquals($userId, $data['ownerUserId']);
//        $this->assertNotNull($data['createdAt']);
//    }

    protected function getEM(): EntityManagerInterface
    {
        return static::$kernel->getContainer()->get('doctrine.orm.entity_manager');
    }
}
