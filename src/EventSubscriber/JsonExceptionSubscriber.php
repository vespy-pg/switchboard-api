<?php

namespace App\EventSubscriber;

use App\Logging\Debug;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class JsonExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $appEnv,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        // Only handle API routes (routes starting with /api or /auth)
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/api') && !str_starts_with($path, '/auth')) {
            return;
        }

        $exception = $event->getThrowable();

        // Determine status code
        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
        }

        // Build error response
        $errorData = [
            'error' => $this->getErrorType($statusCode),
            'message' => $exception->getMessage(),
        ];

        // Add exception cause (class name)
        $reflection = new \ReflectionClass($exception->getPrevious() ?: $exception);
        $errorData['cause'] = $reflection->getShortName();

        // Include detailed information in development environment
        if ($this->appEnv === 'dev') {
            // Add hint with detailed exception messages from the chain and request context
            $errorData['hint'] = $this->getDetailedHint($exception, $request);
            $errorData['trace'] = $exception->getTraceAsString();
            $errorData['file'] = $exception->getFile();
            $errorData['line'] = $exception->getLine();
        }

        $response = new JsonResponse($errorData, $statusCode);
        $this->logger->error($exception);
        $event->setResponse($response);
    }

    private function getErrorType(int $statusCode): string
    {
        return match ($statusCode) {
            Response::HTTP_BAD_REQUEST => 'Bad Request',
            Response::HTTP_UNAUTHORIZED => 'Unauthorized',
            Response::HTTP_FORBIDDEN => 'Forbidden',
            Response::HTTP_NOT_FOUND => 'Not Found',
            Response::HTTP_METHOD_NOT_ALLOWED => 'Method Not Allowed',
            Response::HTTP_CONFLICT => 'Conflict',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'Unprocessable Entity',
            Response::HTTP_TOO_MANY_REQUESTS => 'Too Many Requests',
            default => 'Internal Server Error',
        };
    }

    private function getDetailedHint(\Throwable $exception, $request): string
    {
        $hints = [];

        // Add request context
        $hints[] = sprintf('Method: %s', $request->getMethod());
        $hints[] = sprintf('Path: %s', $request->getPathInfo());

        // Extract resource info from path (e.g., /api/gift_list_items/123)
        if (preg_match('#/api/([^/]+)(?:/([^/]+))?#', $request->getPathInfo(), $matches)) {
            $hints[] = sprintf('Resource: %s', $matches[1]);
            if (isset($matches[2])) {
                $hints[] = sprintf('ID: %s', $matches[2]);
            }
        }

        // Check authentication status
        $user = $request->getUser();
        if ($user) {
            $hints[] = sprintf('User: %s', method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : 'authenticated');
            if (method_exists($user, 'getRoles')) {
                $hints[] = sprintf('Roles: %s', implode(', ', $user->getRoles()));
            }
        } else {
            $hints[] = 'User: NOT AUTHENTICATED';
        }

        // Walk through the exception chain and collect all messages
        $current = $exception;
        $messages = [];

        while ($current !== null) {
            $message = $current->getMessage();
            $className = (new \ReflectionClass($current))->getShortName();

            // Add message with context
            if ($message) {
                $messages[] = sprintf('[%s] %s', $className, $message);
            }

            $current = $current->getPrevious();
        }

        if (!empty($messages)) {
            $hints[] = 'Exception chain: ' . implode(' → ', array_reverse($messages));
        }

        // Return all hints joined
        return implode(' | ', $hints);
    }
}
