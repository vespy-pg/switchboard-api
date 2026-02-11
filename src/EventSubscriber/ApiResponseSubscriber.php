<?php

namespace App\EventSubscriber;

use ApiPlatform\Doctrine\Orm\Paginator;
use ApiPlatform\Metadata\Operation;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiResponseSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly RequestStack $requestStack, private readonly ParameterBagInterface $params)
    {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        /** @var Operation $operation */
        $operation = $this->requestStack->getCurrentRequest()->get('_api_operation');

        /** @var Paginator */
        $paginator = $this->requestStack->getCurrentRequest()->attributes->get('data');
        if (!$paginator instanceof Paginator) {
            return;
        }
        if (!str_starts_with($response->headers->get('Content-Type'), 'application/ld+json')) {
            return;
        }
        $data = json_decode($response->getContent(), true);
        if (isset($data['@context']) && isset($data['totalItems'])) {
            $data['itemsPerPage'] = $paginator->getQuery()->getMaxResults();
            $data['itemsPerPageEnabled'] = $operation->getPaginationClientItemsPerPage();
            $response->setContent(json_encode($data));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ResponseEvent::class => 'onKernelResponse',
        ];
    }
}
