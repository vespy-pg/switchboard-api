<?php

namespace App\Controller\Auth;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Controller\AbstractController;
use App\DTO\Auth\PasswordLoginRequest;
use App\DTO\Auth\RegisterRequest;
use App\Entity\User;
use App\Security\ProgrammaticLoginAuthenticator;
use App\Security\UserProvider;
use App\Service\AuthService;
use DateInterval;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

#[Route('/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly UserProvider $userProvider,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly UserAuthenticatorInterface $userAuthenticator,
        private readonly ProgrammaticLoginAuthenticator $programmaticLoginAuthenticator,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    #[Route('/register', name: 'auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        /** @var RegisterRequest $requestData */
        $requestData = $this->validateRequest($request, RegisterRequest::class, 'auth:register');

        try {
            $user = $this->authService->registerVerifiedUser($request, $requestData);

            $this->userProvider->loadUserRoles($user);

            // Proper Symfony login (session-based)
            $this->userAuthenticator->authenticateUser(
                $user,
                $this->programmaticLoginAuthenticator,
                $request,
            );

            // Persistent cookie + DB session row (multiple sessions allowed)
            $cookie = $this->authService->createSessionCookieForUser(
                $user,
                $request,
                new DateInterval('P365D'),
            );

            $csrfToken = $this->csrfTokenManager->getToken('authenticate')->getValue();

            $response = $this->responseSuccess($user, $request);

            $response->headers->setCookie($cookie);
            $response->headers->set('Cache-Control', 'no-store');

            return $response;
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/password-login', name: 'auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        /** @var PasswordLoginRequest $requestData */
        $requestData = $this->validateRequest($request, PasswordLoginRequest::class, 'auth:login');

        $user = $this->authService->authenticateByPassword(
            $requestData->email,
            $requestData->password,
        );

        if (!$user instanceof User) {
            // Do NOT clear user_identity here; wrong password doesn't mean cookie is invalid
            return new JsonResponse(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        $this->userProvider->loadUserRoles($user);

        $this->userAuthenticator->authenticateUser(
            $user,
            $this->programmaticLoginAuthenticator,
            $request,
        );

        // Rotate persistent cookie on each successful password login (recommended)
        $cookie = $this->authService->createSessionCookieForUser(
            $user,
            $request,
            new DateInterval('P365D'),
        );

        $csrfToken = $this->csrfTokenManager->getToken('authenticate')->getValue();

        $response = $this->responseSuccess($user, $request);

        $response->headers->setCookie($cookie);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/logout', name: 'auth_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        // revoke DB session by user_identity
        $this->authService->revokeUserSession($request);

        // clear in-memory token for this request
        $this->tokenStorage->setToken(null);

        $sessionName = null;
        if ($request->hasSession()) {
            $session = $request->getSession();
            $sessionName = $session->getName();

            // IMPORTANT: remove the security token stored in session
            $session->remove('_security_main');

            // now invalidate session storage
            $session->invalidate();
        }

        $response = new JsonResponse(null, Response::HTTP_NO_CONTENT);
        $response->headers->set('Cache-Control', 'no-store');

        // clear your persistent cookie
        $response->headers->clearCookie(AuthService::COOKIE_NAME, '/', null, $request->isSecure(), true, 'Lax');

        // clear session cookie
        if ($sessionName) {
            $response->headers->clearCookie($sessionName, '/', null, $request->isSecure(), true, 'Lax');
        }

        return $response;
    }

    #[Route('/me', name: 'auth_me', methods: ['GET'])]
    public function me(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return new JsonResponse([
                'error' => 'Not authenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->responseSuccess($user, $request);
    }

    #[Route('/csrf', name: 'auth_csrf', methods: ['GET'])]
    public function csrf(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return new JsonResponse([
                'error' => 'Not authenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $csrfToken = $this->csrfTokenManager->getToken('authenticate')->getValue();

        return $this->responseSuccess(['token' => $csrfToken], $request);
    }
}
