<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RemoveGroupOverrideSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only target non-GET requests (POST, PATCH)
        if (!$request->isMethod('GET')) {
            // Store the removed groups before deleting them
            $removedGroups = $request->query->all('groups') ?? [];
            $request->attributes->set('_removed_groups', $removedGroups);

            // Remove groups from query parameters for write operations
            $request->query->remove('groups');
        }
    }
}
