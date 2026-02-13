<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class IncludeRemovedDoctrineFilterSubscriber implements EventSubscriberInterface
{
    private const FILTER_NAME = 'removed_at_soft_delete';
    private const INCLUDE_REMOVED_GROUP = 'include_removed';

    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isMethod('GET')) {
            return;
        }

        $allQueryParams = $request->query->all();
        $groups = $allQueryParams['groups'] ?? [];

        if (is_string($groups)) {
            $groups = [$groups];
        }

        if (!is_array($groups) || !in_array(self::INCLUDE_REMOVED_GROUP, $groups, true)) {
            return;
        }

        $entityManager = $this->managerRegistry->getManager();
        $filters = $entityManager->getFilters();

        if ($filters->isEnabled(self::FILTER_NAME)) {
            $filters->disable(self::FILTER_NAME);
        }
    }
}
