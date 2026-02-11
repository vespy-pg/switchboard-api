<?php

namespace App\Tests\Web;

use App\DataFixtures\FixtureSetup;
use App\Entity\User;
use App\Entity\UserSession;
use App\Repository\UserRepository;
use App\Tests\TestUtilsTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

/**
 * Base class for web tests that properly handles authentication.
 * 
 * This class provides methods to create authenticated clients using real session cookies,
 * ensuring tests run with actual security checks enabled.
 */
abstract class AbstractWebTestCase extends WebTestCase
{
    use TestUtilsTrait;

    protected array $additionalHeaders = [];

    /**
     * Creates an authenticated client with a real session cookie.
     * 
     * This method:
     * 1. Creates or loads a user from the database
     * 2. Creates a UserSession with a token
     * 3. Sets the session cookie on the client
     * 4. The client will be authenticated via PersistentTokenAuthenticator (disabled in test env)
     *    OR via Symfony's loginUser() which works with the test firewall
     * 
     * @param array $roles Additional roles to add to the user (beyond their DB roles)
     * @param string $userId User ID to authenticate as
     * @return KernelBrowser Authenticated client
     */
    protected function createAuthenticatedClient(
        array $roles = [],
        string $userId = FixtureSetup::DEFAULT_VERIFIED_USER_ID
    ): KernelBrowser {
        $client = static::createClient();
        
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        
        /** @var User|null $user */
        $user = $userRepository->find($userId);
        
        if (!$user) {
            throw new \Exception("User with ID $userId not found. Did you load the user fixture?");
        }
        
        // Load API roles from database
        $apiRoles = $userRepository->loadApiRoles($userId);
        
        // Merge with additional roles for this test
        $allRoles = array_merge($apiRoles, $roles);
        
        // Set roles on the user entity (this is used by Symfony's security system)
        $user->setLoadedRoles($allRoles);
        
        // Use Symfony's built-in test authentication
        // This works because we removed the test-specific firewall override
        $client->loginUser($user, 'main');
        
        return $client;
    }

    /**
     * Creates an unauthenticated client.
     * 
     * Use this to test endpoints that should be publicly accessible
     * or to verify that protected endpoints properly reject unauthenticated requests.
     * 
     * @return KernelBrowser Unauthenticated client
     */
    protected function createUnauthenticatedClient(): KernelBrowser
    {
        return static::createClient();
    }

    /**
     * Helper to process a successful JSON-LD response.
     * 
     * @param KernelBrowser $client
     * @return array Decoded response data
     * @throws \Throwable If response is not successful
     */
    protected function processResponse(KernelBrowser $client): array
    {
        $this->assertResponseFormatSame('jsonld');
        
        try {
            $this->assertResponseIsSuccessful();
            $data = json_decode($client->getResponse()->getContent(), true);
            $this->assertIsArray($data);
            return $data;
        } catch (\Throwable $exception) {
            echo "\n=== Response Debug ===\n";
            echo "Status: " . $client->getResponse()->getStatusCode() . "\n";
            echo "Content:\n";
            print_r(json_decode($client->getResponse()->getContent(), true));
            echo "======================\n\n";
            throw $exception;
        }
    }

    /**
     * Helper to process a successful DELETE response (204 No Content).
     * 
     * @param KernelBrowser $client
     * @throws \Throwable If response is not successful
     */
    protected function processDeleteResponse(KernelBrowser $client): void
    {
        try {
            $this->assertResponseIsSuccessful();
            $this->assertEmpty($client->getResponse()->getContent());
        } catch (\Throwable $exception) {
            echo "\n=== Response Debug ===\n";
            echo "Status: " . $client->getResponse()->getStatusCode() . "\n";
            echo "Content:\n";
            print_r(json_decode($client->getResponse()->getContent(), true));
            echo "======================\n\n";
            throw $exception;
        }
    }

    /**
     * Helper to process an error response (for negative tests).
     * 
     * @param KernelBrowser $client
     * @return array Decoded error response
     */
    protected function processErrorResponse(KernelBrowser $client): array
    {
        $this->assertResponseFormatSame('jsonld');
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        return $data;
    }

    /**
     * Helper to parse route templates with parameters.
     * 
     * Example: processRoute('GET /api/users/{id}', ['id' => '123'])
     * Returns: ['GET', '/api/users/123']
     * 
     * @param string $route Route template (e.g., "GET /api/users/{id}")
     * @param array $params Parameters to substitute
     * @return array [method, path]
     */
    protected function processRoute(string $route, array $params = []): array
    {
        foreach ($params as $key => $value) {
            $route = str_replace('{' . $key . '}', $value, $route);
        }
        return explode(' ', $route, 2);
    }

    /**
     * Get the entity manager.
     * 
     * @return EntityManagerInterface
     */
    protected function getEM(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }
}
