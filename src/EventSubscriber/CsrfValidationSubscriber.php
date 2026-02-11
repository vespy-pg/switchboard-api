<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;

class CsrfValidationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9], // Before security
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Skip CSRF validation for:
        // - GET, HEAD, OPTIONS requests
        // - /auth/login, /auth/register, /auth/logout (public endpoints)
        // - /auth/csrf (generates tokens)
        // - Profiler and dev routes
        if (
            in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'])
            || str_starts_with($request->getPathInfo(), '/auth/password-login')
            || str_starts_with($request->getPathInfo(), '/auth/register')
            || str_starts_with($request->getPathInfo(), '/auth/logout')
            || str_starts_with($request->getPathInfo(), '/auth/csrf')
            || str_starts_with($request->getPathInfo(), '/_')
            || str_starts_with($request->getPathInfo(), '/docs')
            || str_starts_with($request->getPathInfo(), '/health')
            || str_starts_with($request->getPathInfo(), '/api')
        ) {
            return;
        }

        // For write operations (POST, PUT, PATCH, DELETE), validate CSRF token
        $token = $request->headers->get('X-CSRF-Token');

        if (!$token) {
            $event->setResponse(new JsonResponse([
                'error' => 'Forbidden',
                'message' => 'CSRF token missing',
            ], Response::HTTP_FORBIDDEN));
            return;
        }

        $csrfToken = new CsrfToken('spa-auth', $token);

        if (!$this->csrfTokenManager->isTokenValid($csrfToken)) {
            $event->setResponse(new JsonResponse([
                'error' => 'Forbidden',
                'message' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN));
        }
    }
}
