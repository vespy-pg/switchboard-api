<?php

namespace App\EventSubscriber;

use App\Security\PersistentTokenAuthenticator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ClearPersistentIdentityCookieSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->attributes->get('clear_persistent_identity_cookie') !== true) {
            return;
        }

        $event->getResponse()->headers->setCookie(
            Cookie::create(PersistentTokenAuthenticator::COOKIE_NAME)
                ->withValue('')
                ->withExpires(0)
                ->withPath('/')
                ->withHttpOnly(true)
                ->withSecure($request->isSecure())
                ->withSameSite(Cookie::SAMESITE_LAX)
        );
    }
}
