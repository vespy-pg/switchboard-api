<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $dbStatus = 'ok';

        try {
            // Set a short timeout for the database check (2 seconds)
            $originalTimeout = ini_get('default_socket_timeout');
            ini_set('default_socket_timeout', '2');

            // Simple query to check database connectivity with statement timeout
            $this->connection->executeQuery("SET statement_timeout = '2s'");
            $this->connection->executeQuery('SELECT 1');

            // Restore original timeout
            ini_set('default_socket_timeout', $originalTimeout);
        } catch (\Throwable $e) {
            $dbStatus = 'dead';
            // Restore original timeout even on error
            if (isset($originalTimeout)) {
                ini_set('default_socket_timeout', $originalTimeout);
            }
        }

        return $this->json([
            'api' => 'ok',
            'db' => $dbStatus,
        ]);
    }

    #[Route('/secured', name: 'secured', methods: ['GET'])] //security test

    #[IsGranted('ROLE_TESTT')]
    public function secured(): JsonResponse
    {
        return $this->json(['status' => 'ok']);
    }
}
