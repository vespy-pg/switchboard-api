<?php

namespace App\ApiPlatform;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class OrganizationIriConverter implements IriConverterInterface
{
    private IriConverterInterface $decorated;
    private RequestStack $requestStack;

    public function __construct(
        IriConverterInterface $decorated,
        RequestStack $requestStack
    ) {
        $this->decorated = $decorated;
        $this->requestStack = $requestStack;
    }

    public function getIriFromResource(object|string $resource, int $referenceType = UrlGeneratorInterface::ABS_PATH, ?Operation $operation = null, array $context = []): ?string
    {
//        dump('tuuuuuuu?');
        // Get the organizationId from the current request or context
//        $organizationId = $context['organizationId'] ?? null ?: $this->requestStack->getCurrentRequest()->attributes->get('organizationId');
//
//        if (!$organizationId) {
//            throw new \RuntimeException('Missing organizationId for IRI generation.');
//        }

        // Add organizationId to the context
//        $context['uri_variables'] = array_merge($context['uri_variables'] ?? [], ['organizationId' => $organizationId]);
//
        return $this->decorated->getIriFromResource($resource, $referenceType, $operation);
    }

    public function getResourceFromIri(string $iri, array $context = [], ?Operation $operation = null): object
    {
        return $this->decorated->getResourceFromIri($iri, $context);
    }
}
