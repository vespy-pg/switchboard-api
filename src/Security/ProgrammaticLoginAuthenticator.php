<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final class ProgrammaticLoginAuthenticator extends AbstractAuthenticator
{
    public function supports(Request $request): ?bool
    {
        // This authenticator is not meant to authenticate requests directly.
        // It exists so controllers can call UserAuthenticatorInterface::authenticateUser().
        return false;
    }

    public function authenticate(Request $request): Passport
    {
        // Not used because supports() is false, but must return a valid Passport.
        return new SelfValidatingPassport(new UserBadge('unused'));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Let the controller continue and return its JSON response
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Not used because supports() is false, but keep it safe.
        return null;
    }
}
