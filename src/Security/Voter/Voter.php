<?php

namespace App\Security\Voter;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\User;
use App\Exception\AccessDeniedException;
use ApiPlatform\Doctrine\Orm\Paginator;
use App\Security\Exception\ApiTokenExpiredException;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\Voter\Voter as BaseVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class Voter extends BaseVoter
{
    protected UserInterface $user;
    protected string $operation;

    public function __construct(private LoggerInterface $logger)
    {
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        try {
            // Get the currently authenticated user (or null if not logged in)
            $user = $token->getUser();
            if (!is_object($user) || !$user instanceof UserInterface || !$user->getUserIdentifier()) {
                throw new AccessDeniedException('User is not logged in');
            }
            $this->user = $user;

            if ($subject instanceof Paginator) {
                $method = null;
                foreach ($subject as $key => $item) {
                    if ($key === 0) {
                        $reflection = new ReflectionClass(get_class($this));
                        $method = 'can' . str_replace(str_replace('Voter', '', $reflection->getShortName()), '', ucfirst($this->snakeToCamel($attribute)));
                        $this->requireAttributeRole($attribute, $item);
                    }
                    $this->$method($item);
                }
                return true;
            }
            $reflection = new ReflectionClass(get_class($this));
            $method = 'can' . str_replace(str_replace('Voter', '', $reflection->getShortName()), '', ucfirst($this->snakeToCamel($attribute)));
            $this->requireAttributeRole($attribute, $subject);
            $this->$method($subject);
            return true;
        } catch (AccessDeniedException $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            // Re-throw the exception to preserve the message instead of returning false
            // This allows the detailed error message to reach the error handler
            throw $exception;
        }
    }

    protected function requireRole($role)
    {
        error_log('Required role: ' . $role . ' in roles: ' . implode(',', $this->user->getRoles()));
        if (!in_array($role, $this->user->getRoles())) {
            throw new AccessDeniedException("Missing required role: $role");
        }
    }

    private function requireAttributeRole(string $attribute, $subject): void
    {
        if (!is_object($subject)) {
            return;
        }
        // If attribute already starts with ROLE_, use it as-is
        // Otherwise, add ROLE_ prefix for backward compatibility
        $role = str_starts_with($attribute, 'ROLE_') ? $attribute : 'ROLE_' . $attribute;
        $this->requireRole($role);
    }

    protected function camelToUpperSnake(string $input): string
    {
        return strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    protected function snakeToCamel(string $input): string
    {
        // Convert UPPER_SNAKE_CASE or snake_case to camelCase
        return lcfirst(str_replace('_', '', ucwords(strtolower($input), '_')));
    }

    final protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, $this->supportedAttributes);
    }
}
