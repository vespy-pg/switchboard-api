<?php

namespace App\Tests\Web;

use App\DataFixtures\UserFixture;
use Symfony\Component\HttpFoundation\Response;

/**
 * Example web tests demonstrating proper authentication testing.
 * 
 * These tests show how to:
 * - Test authenticated endpoints with real security
 * - Test unauthenticated access (should be denied)
 * - Test role-based access control
 * - Use fixtures to create test data
 */
class ExampleWebTest extends AbstractWebTestCase
{
    /**
     * Test that unauthenticated requests are properly rejected.
     * 
     * This verifies that security is actually working - the endpoint
     * should return 401 Unauthorized when no authentication is provided.
     */
    public function testUnauthenticatedAccessIsDenied(): void
    {
        $client = $this->createUnauthenticatedClient();
        
        // Try to access a protected endpoint without authentication
        $client->request('GET', '/api/users');
        
        // Should be rejected with 401 Unauthorized
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Test authenticated access to list users.
     * 
     * This test:
     * 1. Creates a user fixture
     * 2. Authenticates as that user
     * 3. Makes a request to list users
     * 4. Verifies the response is successful
     */
    public function testAuthenticatedUserCanListUsers(): void
    {
        // Create a test user
        $userId = $this->loadFixture(UserFixture::class);
        
        // Create an authenticated client for this user
        // The user will have whatever roles are assigned in the database
        $client = $this->createAuthenticatedClient([], $userId);
        
        // Make a request to list users
        $client->request('GET', '/api/users', ['id' => [$userId]]);
        
        // Verify the response
        $this->assertResponseIsSuccessful();
        $this->assertResponseFormatSame('jsonld');
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('member', $data);
    }

    /**
     * Test authenticated access to view a specific user.
     */
    public function testAuthenticatedUserCanViewUser(): void
    {
        $userId = $this->loadFixture(UserFixture::class);
        $client = $this->createAuthenticatedClient([], $userId);
        
        $client->request('GET', "/api/users/$userId");
        
        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals($userId, $data['id']);
    }

    /**
     * Test creating a user with authentication.
     * 
     * This demonstrates testing POST requests with JSON payloads.
     */
    public function testAuthenticatedUserCanCreateUser(): void
    {
        $userId = $this->loadFixture(UserFixture::class);
        $client = $this->createAuthenticatedClient([], $userId);
        
        $client->request(
            'POST',
            '/api/users',
            [],
            [],
            ['CONTENT_TYPE' => 'application/ld+json'],
            json_encode([
                'name' => 'Test User',
                'isActive' => true,
                'email' => $this->uuidV4() . '@example.com',
                'password' => 'securepassword123'
            ])
        );
        
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('Test User', $data['name']);
    }

    /**
     * Test updating a user with PATCH request.
     */
    public function testAuthenticatedUserCanUpdateUser(): void
    {
        $userId = $this->loadFixture(UserFixture::class);
        $client = $this->createAuthenticatedClient([], $userId);
        
        $client->request(
            'PATCH',
            "/api/users/$userId",
            [],
            [],
            ['CONTENT_TYPE' => 'application/merge-patch+json'],
            json_encode([
                'name' => 'Updated Name'
            ])
        );
        
        $this->assertResponseIsSuccessful();
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Updated Name', $data['name']);
    }

    /**
     * Test deleting a user.
     */
    public function testAuthenticatedUserCanDeleteUser(): void
    {
        $userId = $this->loadFixture(UserFixture::class);
        $client = $this->createAuthenticatedClient([], $userId);
        
        $client->request('DELETE', "/api/users/$userId");
        
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    /**
     * Test role-based access control.
     * 
     * This demonstrates how to test with additional roles.
     * The user gets their database roles PLUS any additional roles you specify.
     */
    public function testUserWithSpecificRoleCanAccessEndpoint(): void
    {
        $userId = $this->loadFixture(UserFixture::class);
        
        // Create client with additional role for this test
        $client = $this->createAuthenticatedClient(['ROLE_ADMIN'], $userId);
        
        // Now this user has ROLE_ADMIN in addition to their database roles
        $client->request('GET', '/api/users');
        
        $this->assertResponseIsSuccessful();
    }

    /**
     * Test that validation errors are properly returned.
     * 
     * This demonstrates testing error responses.
     */
    public function testValidationErrorsAreReturned(): void
    {
        $userId = $this->loadFixture(UserFixture::class);
        $client = $this->createAuthenticatedClient([], $userId);
        
        // Try to create a user with invalid data (missing required fields)
        $client->request(
            'POST',
            '/api/users',
            [],
            [],
            ['CONTENT_TYPE' => 'application/ld+json'],
            json_encode([
                'name' => 'Test User'
                // Missing email and password
            ])
        );
        
        // Should return 422 Unprocessable Entity for validation errors
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('violations', $data);
    }

    /**
     * Test using the helper methods from AbstractWebTestCase.
     */
    public function testUsingHelperMethods(): void
    {
        $userId = $this->loadFixture(UserFixture::class);
        $client = $this->createAuthenticatedClient([], $userId);
        
        $client->request('GET', "/api/users/$userId");
        
        // Use the processResponse helper
        $data = $this->processResponse($client);
        
        $this->assertEquals($userId, $data['id']);
    }

    /**
     * Test multiple users with different permissions.
     * 
     * This shows how to test scenarios with multiple users.
     */
    public function testMultipleUsersWithDifferentPermissions(): void
    {
        // Create two different users
        $user1Id = $this->loadFixture(UserFixture::class, ['user1@example.com']);
        $user2Id = $this->loadFixture(UserFixture::class, ['user2@example.com']);
        
        // Test as user 1
        $client1 = $this->createAuthenticatedClient([], $user1Id);
        $client1->request('GET', "/api/users/$user1Id");
        $this->assertResponseIsSuccessful();
        
        // Test as user 2
        $client2 = $this->createAuthenticatedClient([], $user2Id);
        $client2->request('GET', "/api/users/$user2Id");
        $this->assertResponseIsSuccessful();
    }
}
