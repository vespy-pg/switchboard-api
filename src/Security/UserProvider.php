<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
    private array $rolesCache = [];

    public function __construct(
        private readonly UserRepository $userRepository
    ) {
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        $refreshedUser = $this->userRepository->find($user->getId());

        if (!$refreshedUser) {
            throw new UserNotFoundException(sprintf('User with ID "%s" not found.', $user->getId()));
        }

        // Load roles to match what was in the original token
        // This uses the cache, so if roles were loaded during login, they'll be the same
        $this->loadUserRoles($refreshedUser);

        return $refreshedUser;
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->userRepository->find($identifier);

        if (!$user) {
            throw new UserNotFoundException(sprintf('User with ID "%s" not found.', $identifier));
        }

        // Check if user is active and not removed
        if ($user->getRemovedAt() !== null) {
            throw new UserNotFoundException('User account has been removed.');
        }

        // Load and cache API roles from database
        $this->loadUserRoles($user);

        return $user;
    }

    public function loadUserRoles(User $user): void
    {
        $userId = $user->getId();

        if (!$userId) {
            throw new UserNotFoundException(sprintf('User with ID "%s" not found.', $user->getId()));
        }

        // Check cache first
        if (isset($this->rolesCache[$userId]['api']) && isset($this->rolesCache[$userId]['ui'])) {
            $user->setLoadedRoles($this->rolesCache[$userId]['api'], $this->rolesCache[$userId]['ui']);
            return;
        }

        // Load API roles from database (groups/roles system)
        $apiRoles = $this->userRepository->loadRoles($userId, UserRepository::ROLE_API);
        $uiRoles = $this->userRepository->loadRoles($userId, UserRepository::ROLE_UI);

        // Cache the roles
        $this->rolesCache[$userId]['api'] = $apiRoles;
        $this->rolesCache[$userId]['ui'] = $uiRoles;

        // Set roles on user entity
        $user->setLoadedRoles($apiRoles, $uiRoles);
    }

    public function getUserRoles(User $user): array
    {
        $userId = $user->getId();

        // Get base roles from User entity
        $roles = $user->getRoles();

        // Add roles from database if cached
        if (isset($this->rolesCache[$userId])) {
            $roles = array_merge($roles, $this->rolesCache[$userId]);
        }

        return array_unique($roles);
    }
}
