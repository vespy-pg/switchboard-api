<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class OrganizationFilterSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER_ARGUMENTS => 'onKernelControllerArguments',
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $organizationId = $request->attributes->get('organizationId');
        if (!$organizationId) {
            $request->attributes->set('/organizationId', 'default');
            return;
        }
        $request->attributes->set('/organizationId', $organizationId);

        $filters = $request->attributes->get('_api_filters', []);
        $filters['organization'] = $organizationId;
        $request->attributes->set('_api_filters', array_merge($request->query->all(), $filters));
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->attributes->get('organizationId')) {
            $request->attributes->set('organizationId', 'defaultOrganizationId');
        }
    }
}
