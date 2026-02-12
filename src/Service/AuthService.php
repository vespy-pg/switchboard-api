<?php

namespace App\Service;

use App\DTO\Auth\RegisterRequest;
use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserGroup;
use App\Entity\UserSession;
use App\Event\UserCreatedEvent;
use App\Repository\UserRepository;
use App\Repository\UserSessionRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

class AuthService
{
    public const COOKIE_NAME = 'user_identity';
    private const TOKEN_EXPIRY_DAYS = 365;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserSessionRepository $sessionRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * Register a verified user with email and password
     */
    public function registerVerifiedUser(Request $request, RegisterRequest $data): User
    {
        // Normalize email
        $normalizedEmail = strtolower(trim($data->email));

        // Check if user already exists
        $existingUser = $this->userRepository->findActiveUserByEmail($normalizedEmail);
        if ($existingUser) {
            throw new InvalidArgumentException('User with this email already exists.');
        }

        // Create user
        $user = new User();
        $user->setEmail($normalizedEmail);
        $user->setFirstName($data->firstName);
        $user->setIsVerified(false);
        $user->setEmailVerifiedAt(new DateTimeImmutable());
        $user->setCreatedAt(new DateTimeImmutable());

        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $data->password);
        $user->setPasswordHash($hashedPassword);

        $this->entityManager->persist($user);

        $userGroup = new UserGroup();
        $userGroup->setUser($user);
        $userGroup->setGroup($this->entityManager->getReference(Group::class, Group::BASIC));
        $userGroup->setIsActive(true);
        $this->entityManager->persist($userGroup);

        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(
            new UserCreatedEvent(
                createdUserId: $user->getId(),
                userId: $user->getId()
            )
        );

        $this->entityManager->refresh($user);

        return $user;
    }

    /**
     * Authenticate user by email and password
     */
    public function authenticateByPassword(string $email, string $password): ?User
    {
        $normalizedEmail = strtolower(trim($email));
        $user = $this->userRepository->findActiveUserByEmail($normalizedEmail);

        if (!$user) {
            return null;
        }

        // Verify password
        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            return null;
        }

        // Update last login
        $user->setLastLoginAt(new DateTimeImmutable());
        $this->entityManager->flush();

        return $user;
    }

    /**
     * Revoke all sessions for a user
     */
    public function revokeAllUserSessions(string $userId): void
    {
        $this->sessionRepository->revokeAllUserSessions($userId);
    }

    /**
     * Create a cookie to clear the persistent identity
     */
    public function createClearCookie(): Cookie
    {
        return Cookie::create(self::COOKIE_NAME)
            ->withValue('')
            ->withExpires(new \DateTime('-1 day'))
            ->withPath('/')
            ->withSecure(true)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }

    /**
     * Creates a new persistent session and returns the cookie to set on the response.
     */
    public function createSessionCookieForUser(User $user, Request $request, \DateInterval $validFor): Cookie
    {
        $rawToken = $this->generateRawToken();
        $tokenHash = hash('sha256', $rawToken);

        $expiresAt = (new DateTimeImmutable())->add($validFor);

        $userSession = new UserSession();
        $userSession->setUser($user);
        $userSession->setTokenHash($tokenHash);
        $userSession->setIpAddress($request->getClientIp());
        $userSession->setLastIpAddress($request->getClientIp());
        $userSession->setLastUsedAt(new DateTimeImmutable());
        $userSession->setCreatedAt(new DateTimeImmutable());
        $userSession->setExpiresAt($expiresAt);
        $userSession->setIsTrusted(false);
        $userSession->setUserAgent($request->headers->get('User-Agent'));

        $this->entityManager->persist($userSession);
        $this->entityManager->flush();

        $isSecure = $request->isSecure();

        return Cookie::create(self::COOKIE_NAME)
            ->withValue($rawToken)
            ->withExpires($expiresAt)
            ->withPath('/')
            ->withHttpOnly()
            ->withSecure($isSecure)
            ->withSameSite('Lax');
    }

    private function generateRawToken(): string
    {
        // 32 random bytes -> 43-ish chars base64url, good for cookies
        $binary = random_bytes(32);
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    public function revokeUserSession(Request $request): void
    {
        $rawToken = $request->cookies->get(self::COOKIE_NAME);
        if (!$rawToken) {
            return;
        }

        $userSession = $this->entityManager->getRepository(UserSession::class)->findOneBy([
            'tokenHash' => hash('sha256', $rawToken),
        ]);

        if (!$userSession) {
            return;
        }

        $userSession->setRemovedAt(new DateTimeImmutable('now'));
        $this->entityManager->flush();
    }
}
