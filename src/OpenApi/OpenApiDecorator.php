<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\Model\Schema;
use ApiPlatform\OpenApi\OpenApi;
use ArrayObject;
use ReflectionClass;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

/**
 * OpenAPI decorator that:
 * 1) Appends serializer group info to each operation description (as HTML).
 * 2) Patches component schemas to document relation properties (typed as objects) as OPTIONAL,
 *    even if they are NOT part of the default normalization groups.
 *
 * This is designed for the use case:
 * - Default responses omit relations entirely unless `?groups[]=...` is provided (no IRI fallback).
 * - Docs should still show relations as objects, with a note that they are only serialized when groups are requested.
 *
 * Notes:
 * - OpenAPI is static, so we cannot truly express "property exists only when query param includes group".
 *   We document it as optional and add a description hint with the enabling groups.
 * - API Platform internal schema storage varies by version; this implementation safely handles Schema, array and ArrayObject.
 */
final class OpenApiDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decoratedOpenApiFactory,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly ClassMetadataFactoryInterface $classMetadataFactory
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decoratedOpenApiFactory)($context);

        $openApi = $this->appendSerializationGroupsToOperationDescriptions($openApi);
        $openApi = $this->patchSchemasWithOptionalRelationObjects($openApi);

        return $openApi;
    }

    private function appendSerializationGroupsToOperationDescriptions(OpenApi $openApi): OpenApi
    {
        foreach ($openApi->getPaths()->getPaths() as $path => $pathItem) {
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $operationName) {
                $operationGetter = 'get' . ucfirst($operationName);
                $operationWither = 'with' . ucfirst($operationName);

                if (!method_exists($pathItem, $operationGetter) || !method_exists($pathItem, $operationWither)) {
                    continue;
                }

                $operation = $pathItem->{$operationGetter}();
                if ($operation === null) {
                    continue;
                }

                $firstTag = $operation->getTags()[0] ?? null;
                $resourceClass = $this->guessResourceClassFromTag($firstTag);
                if ($resourceClass === null) {
                    continue;
                }

                $groups = $this->getAllSerializerGroupsUsedByClass($resourceClass);

                $existingDescription = $operation->getDescription() ?: '';
                $groupsHtmlList = implode('', array_map(
                    static fn(string $groupName): string => '<li>' . htmlspecialchars($groupName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>',
                    $groups
                ));

                $operation = $operation->withDescription(
                    $existingDescription
                    . "\n\nSerialization groups:<br /><ul>"
                    . $groupsHtmlList
                    . '</ul>'
                );

                $pathItem = $pathItem->{$operationWither}($operation);
            }

            $openApi->getPaths()->addPath($path, $pathItem);
        }

        return $openApi;
    }

    private function patchSchemasWithOptionalRelationObjects(OpenApi $openApi): OpenApi
    {
        $components = $openApi->getComponents();
        if ($components === null) {
            return $openApi;
        }

        $schemas = $components->getSchemas();
        if (!$schemas instanceof ArrayObject || $schemas->count() === 0) {
            return $openApi;
        }

        foreach ($schemas as $schemaKey => $schemaValue) {
            $schemaKeyString = (string)$schemaKey;

            $resourceClass = $this->guessResourceClassFromSchemaKey($schemaKeyString);
            if ($resourceClass === null) {
                continue;
            }

            $relationProperties = $this->getRelationPropertiesWithGroups($resourceClass);
            if ($relationProperties === []) {
                continue;
            }

            $schemaArray = $this->schemaToArray($schemaValue);
            if ($schemaArray === []) {
                continue;
            }

            $schemaProperties = $schemaArray['properties'] ?? [];
            if (!is_array($schemaProperties)) {
                $schemaProperties = [];
            }

            $schemaRequired = $schemaArray['required'] ?? [];
            if (!is_array($schemaRequired)) {
                $schemaRequired = [];
            }

            foreach ($relationProperties as $propertyName => $relationInfo) {
                $targetSchemaReference = $this->guessSchemaReferenceForClass($relationInfo['targetClass'], $schemas);
                if ($targetSchemaReference === null) {
                    continue;
                }

                $schemaProperties[$propertyName] = [
                    'nullable' => true,
                    'allOf' => [
                        ['$ref' => $targetSchemaReference],
                    ],
                    'description' => sprintf(
                        'Serialized only when group(s) provided: %s',
                        implode(', ', $relationInfo['groups'])
                    ),
                ];

                // Ensure optional (remove from required)
                $schemaRequired = array_values(array_filter(
                    $schemaRequired,
                    static fn(string $requiredPropertyName): bool => $requiredPropertyName !== $propertyName
                ));
            }

            $schemaArray['properties'] = $schemaProperties;

            if ($schemaRequired !== []) {
                $schemaArray['required'] = $schemaRequired;
            } else {
                unset($schemaArray['required']);
            }

            $schemas[$schemaKeyString] = new Schema($schemaArray);
        }

        $newComponents = new Components($schemas, $components->getSecuritySchemes());

        return $openApi->withComponents($newComponents);
    }

    private function guessResourceClassFromTag(?string $tag): ?string
    {
        if ($tag === null || $tag === '') {
            return null;
        }

        $entityClass = 'App\\Entity\\' . $tag;
        if (class_exists($entityClass)) {
            return $entityClass;
        }

        $apiResourceClass = 'App\\ApiResource\\' . $tag;
        if (class_exists($apiResourceClass)) {
            return $apiResourceClass;
        }

        return null;
    }

    private function guessResourceClassFromSchemaKey(string $schemaKey): ?string
    {
        // API Platform schema keys often look like:
        // - "UserDevice.jsonld-read"
        // - "UserDevice-read"
        // - "UserDevice"
        $baseName = preg_split('/[.\-]/', $schemaKey)[0] ?? null;
        if ($baseName === null || $baseName === '') {
            return null;
        }

        $entityClass = 'App\\Entity\\' . $baseName;
        if (class_exists($entityClass)) {
            return $entityClass;
        }

        $apiResourceClass = 'App\\ApiResource\\' . $baseName;
        if (class_exists($apiResourceClass)) {
            return $apiResourceClass;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function getAllSerializerGroupsUsedByClass(string $resourceClass): array
    {
        $serializerClassMetadata = $this->classMetadataFactory->getMetadataFor($resourceClass);
        $groups = [];

        foreach ($serializerClassMetadata->getAttributesMetadata() as $attributeMetadata) {
            $groups = array_merge($groups, $attributeMetadata->getGroups());
        }

        $groups = array_values(array_unique($groups));
        sort($groups);

        return $groups;
    }

    /**
     * Relation detection heuristic:
     * - if a property exists on the class
     * - and it has a non-builtin PHP type-hint that resolves to a class name
     * - and it has at least one serializer group assigned
     * then treat it as a relation-like property for schema patching.
     *
     * @return array<string, array{targetClass: string, groups: array<int, string>}>
     */
    private function getRelationPropertiesWithGroups(string $resourceClass): array
    {
        $reflectionClass = new ReflectionClass($resourceClass);
        $serializerClassMetadata = $this->classMetadataFactory->getMetadataFor($resourceClass);

        $relations = [];

        foreach ($serializerClassMetadata->getAttributesMetadata() as $attributeName => $attributeMetadata) {
            if (!is_string($attributeName) || !$reflectionClass->hasProperty($attributeName)) {
                continue;
            }

            $reflectionProperty = $reflectionClass->getProperty($attributeName);
            $reflectionType = $reflectionProperty->getType();

            if ($reflectionType === null || $reflectionType->isBuiltin()) {
                continue;
            }

            $targetClassName = $reflectionType->getName();
            if (!class_exists($targetClassName)) {
                continue;
            }

            $groups = array_values(array_unique($attributeMetadata->getGroups()));
            if ($groups === []) {
                continue;
            }

            $relations[$attributeName] = [
                'targetClass' => $targetClassName,
                'groups' => $groups,
            ];
        }

        return $relations;
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaToArray(mixed $schemaValue): array
    {
        if ($schemaValue instanceof Schema) {
            return [
                'type' => $schemaValue->getType(),
                'properties' => $schemaValue->getProperties() ?? [],
                'required' => $schemaValue->getRequired() ?? [],
                'description' => $schemaValue->getDescription(),
                'nullable' => $schemaValue->isNullable(),
            ];
        }

        if ($schemaValue instanceof ArrayObject) {
            return $schemaValue->getArrayCopy();
        }

        if (is_array($schemaValue)) {
            return $schemaValue;
        }

        return [];
    }

    private function guessSchemaReferenceForClass(string $className, ArrayObject $schemas): ?string
    {
        $shortName = (new ReflectionClass($className))->getShortName();

        $candidateKeys = [
            $shortName,
            $shortName . '.jsonld-read',
            $shortName . '-read',
        ];

        foreach ($candidateKeys as $candidateKey) {
            if (isset($schemas[$candidateKey])) {
                return '#/components/schemas/' . $candidateKey;
            }
        }

        foreach ($schemas as $schemaKey => $_schemaValue) {
            $schemaKeyString = (string)$schemaKey;
            if (str_starts_with($schemaKeyString, $shortName . '.') || str_starts_with($schemaKeyString, $shortName . '-')) {
                return '#/components/schemas/' . $schemaKeyString;
            }
        }

        return null;
    }
}
