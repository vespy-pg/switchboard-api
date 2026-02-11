<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private readonly string $appEnv
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        // Return null to let the exception bubble up to JsonExceptionSubscriber
        // This allows consistent error formatting with request context
        return null;
    }

    private function getDetailedHint(\Throwable $exception, array $attributes, mixed $subject): string
    {
        $hints = [];

        // Add attribute info
        if (!empty($attributes)) {
            $hints[] = sprintf('Attributes: %s', implode(', ', $attributes));
        }

        // Add subject info
        if ($subject) {
            if (is_object($subject) && !is_array($subject)) {
                $hints[] = sprintf('Subject: %s', (new \ReflectionClass($subject))->getShortName());
            } elseif (is_array($subject)) {
                $hints[] = sprintf('Subject: %s', $this->arrayToString($subject));
            }
        }

        // Walk exception chain
        $current = $exception;
        $exceptionMessages = [];
        while ($current !== null) {
            $className = (new \ReflectionClass($current))->getShortName();
            $msg = $current->getMessage();
            if ($msg) {
                $exceptionMessages[] = sprintf('[%s] %s', $className, $msg);
            }
            $current = $current->getPrevious();
        }

        if (!empty($exceptionMessages)) {
            $hints[] = 'Exception chain: ' . implode(' → ', array_reverse($exceptionMessages));
        }

        return implode(' | ', $hints);
    }

    private function arrayToString(array $subject): string
    {
        $str = '';
        foreach ($subject as $k => $v) {
            $str .= $k . ': ' . (is_array($v) ? '{' . $this->arrayToString($v) . '}' : $v) . '; ';
        }

        return $str;
    }
}
