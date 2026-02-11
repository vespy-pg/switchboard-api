<?php

namespace App\Controller\User;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Controller\AbstractController;
use App\Entity\Organization;
use App\Entity\Team;
use App\Entity\User;
use App\Entity\UserOrganization;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Throwable;

class CreateController extends AbstractController
{
    #[Route(
        path: '/api/organizations/{organizationId}/users',
        name: 'api_user_create',
        methods: ['POST']
    )]
    public function __invoke(Request $request, string $organizationId): JsonResponse
    {
        $data = $request->toArray();
        $user = $this->serializer->deserialize(
            $request->getContent(),
            User::class,
            'json',
            ['groups' => ['create']]
        );
        $errors = $this->validator->validate($user, null, ['create']);
        $errors->addAll($this->validator->validate([
            'team' => $data['team'] ?? null,
            'organization' => $organizationId,
        ], new Collection([
            'team' => new NotBlank(),
            'organization' => new NotBlank(),
        ])));
        if ($errors->count()) {
            throw new ValidationException($errors);
        }
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        try {
            $organization = $this->entityManager->getRepository(Organization::class)->find($organizationId);
            /** @var Team $team */
            $team = $this->iriConverter->getResourceFromIri($data['team']);

            $userOrganization = $this->serializer->deserialize(
                json_encode([
                    'organization' => $this->iriConverter->getIriFromResource($organization),
                    'team' => $this->iriConverter->getIriFromResource($team),
                    // this has to be hardcoded as this user doesn't have an organization yet and can't get user iri without it
                    'user' => "/api/organizations/$organizationId/users/{$user->getId()}",
                    'isDefault' => false,
                    'isActive' => true
                ]),
                UserOrganization::class,
                'json',
                ['groups' => 'create']
            );
            $errors = $this->validator->validate($userOrganization, null, ['create']);

            if ($errors->count()) {
                throw new ValidationException($errors);
            }
        } catch (Throwable $exception) {
            $this->entityManager->remove($user);
            throw $exception;
        }

        $this->entityManager->persist($userOrganization);
        $this->entityManager->flush();
        $collection = new ArrayCollection([$userOrganization]);
        $user->setUserOrganizations($collection);
        $this->entityManager->refresh($user);

        return $this->responseSuccess($user, $request, Response::HTTP_CREATED);
    }
}
