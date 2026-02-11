<?php

namespace App\Controller;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\Attribute\Required;

class AbstractController
{
    protected IriConverterInterface $iriConverter;
    protected EntityManagerInterface $entityManager;
    protected ValidatorInterface $validator;
    protected SerializerInterface $serializer;

    #[Required]
    public function dependencies(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        SerializerInterface $serializer,
        IriConverterInterface $iriConverter
    ) {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->serializer = $serializer;
        $this->iriConverter = $iriConverter;
    }

    protected function validateRequest(
        Request $request,
        string $validationClass,
        string $validationGroup,
        ?ConstraintViolationListInterface $errors = null,
    ): object {
        $object = $this->serializer->deserialize(
            $request->getContent(),
            $validationClass,
            'json',
            ['groups' => [$validationGroup]]
        );
        $currentErrors = $this->validator->validate($object, null, [$validationGroup]);
        if ($errors) {
            $errors->addAll($currentErrors);
        } else {
            $errors = $currentErrors;
        }

        if ($errors->count() > 0) {
            throw new ValidationException($errors);
        }
        return $object;
    }

    public function responseSuccess(mixed $resource, Request $request, int $httpStatus = Response::HTTP_OK): JsonResponse
    {
        if (is_object($resource)) {
            $responseData = json_decode($this->serializer->serialize($resource, 'jsonld', ['groups' => $request->query->all('groups') ?: ['read']]));
        } else {
            $responseData = $resource;
        }

        $headers = [
            'Content-Type' => 'application/ld+json',
        ];
        if (is_object($resource)) {
            $headers['X-Resource-Iri'] = $this->iriConverter->getIriFromResource($resource);
        }
        return new JsonResponse(
            json_encode($responseData),
            $httpStatus,
            $headers,
            true
        );
    }
}
