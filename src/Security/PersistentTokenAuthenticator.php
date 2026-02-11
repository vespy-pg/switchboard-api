<?php

namespace App\Security;

use App\Repository\UserSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

final class PersistentTokenAuthenticator extends AbstractAuthenticator
{
    public const COOKIE_NAME = 'user_identity';

    public function __construct(
        private readonly UserSessionRepository $userSessionRepository,
        private readonly UserProvider $userProvider,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $appEnv,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        if ($this->appEnv === 'test') {
            return false;
        }

        if (str_starts_with($request->getPathInfo(), '/auth/')) {
            return false;
        }

        return $request->cookies->has(self::COOKIE_NAME);
    }

    public function authenticate(Request $request): Passport
    {
        $rawToken = (string) $request->cookies->get(self::COOKIE_NAME, '');

        if (!$rawToken) {
            throw new AuthenticationException('No persistent identity token found.');
        }

        $tokenHash = hash('sha256', $rawToken);

        $userSession = $this->userSessionRepository->findActiveSessionByTokenHash($tokenHash);

        if (!$userSession) {
            $request->attributes->set('clear_persistent_identity_cookie', true);
            throw new AuthenticationException('Invalid or expired persistent identity token.');
        }

        $userSession->setLastUsedAt(new \DateTimeImmutable());
        $userSession->setLastIpAddress($request->getClientIp());
        $this->entityManager->flush();

        $userId = $userSession->getUserId();

        if (!$userId) {
            throw new AuthenticationException('Session has no user.');
        }

        return new SelfValidatingPassport(
            new UserBadge($userId, function (string $userIdentifier) {
                return $this->userProvider->loadUserByIdentifier($userIdentifier);
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->attributes->set('clear_persistent_identity_cookie', true);

        return null;
    }
}
