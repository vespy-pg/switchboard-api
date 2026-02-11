<?php

namespace App\Serializer;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\SerializerContextBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

final class CustomSerializerContextBuilder implements SerializerContextBuilderInterface
{
    private SerializerContextBuilderInterface $decorated;

    public function __construct(SerializerContextBuilderInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function createFromRequest(Request $request, bool $normalization, Operation|array|null $extractedAttributes = null, array $context = []): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes, $context);

        if ($normalization && $request->attributes->has('_removed_groups')) {
            $removedGroups = $request->attributes->get('_removed_groups', ['read']);
            $context['groups'] = array_unique(array_merge($context['groups'] ?? [], $removedGroups));
        }

        return $context;
    }
}
