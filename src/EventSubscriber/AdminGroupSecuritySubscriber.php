<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class AdminGroupSecuritySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only process if groups parameter is present
        if (!$request->query->has('groups')) {
            return;
        }

        $groups = $request->query->all('groups');

        // If 'admin' group is requested but user doesn't have ROLE_ADMIN, remove it
        if (in_array('admin', $groups, true) && !$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $groups = array_filter($groups, fn($group) => $group !== 'admin');
            $request->query->set('groups', $groups);
        }
    }
}
