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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;

class UpdateController extends AbstractController
{
    #[Route(
        path: '/api/organizations/{organizationId}/users/{id}',
        name: 'api_user_create',
        methods: ['POST']
    )]
    public function __invoke(Request $request, string $organizationId, string $id): JsonResponse
    {
        $data = $request->toArray();
        $user = $this->entityManager->getRepository(User::class)->find($id);
        $user = $this->serializer->deserialize(
            $request->getContent(),
            User::class,
            'json',
            ['groups' => ['create'], 'object_to_populate' => $user]
        );
        $errors = $this->validator->validate($user, null, ['update']);
        $errors->addAll($this->validator->validate([
            'organization' => $organizationId,
        ], new Collection([
            'organization' => new NotBlank(),
        ])));
        $organization = $this->entityManager->getRepository(Organization::class)->find($organizationId);
        $userOrganization = $this->entityManager->getRepository(UserOrganization::class)->findOneBy(['user' => $id, 'organization' => $organizationId]);
        if (!$userOrganization) {
            throw new NotFoundHttpException();
        }
        if (array_key_exists('team', $data)) {
            $errors->addAll($this->validator->validate([
                'team' => $data['team'] ?? null,
            ], new Collection([
                'team' => new NotBlank(),
            ])));
        }
        if ($errors->count()) {
            throw new ValidationException($errors);
        }
        $userOrganization = $this->serializer->deserialize(
            json_encode([
                'organization' => $this->iriConverter->getIriFromResource($organization),
                'team' => $data['team'] ?? $this->iriConverter->getIriFromResource($userOrganization->getTeam()),
                'user' => $this->iriConverter->getIriFromResource($user)
            ]),
            UserOrganization::class,
            'json',
            ['groups' => 'update', 'object_to_populate' => $userOrganization]
        );
        $errors = $this->validator->validate($userOrganization, null, ['update']);
        if ($errors->count()) {
            throw new ValidationException($errors);
        }
        $this->entityManager->persist($user);
        $this->entityManager->persist($userOrganization);
        $this->entityManager->flush();
        $collection = new ArrayCollection([$userOrganization]);
        $user->setUserOrganizations($collection);
        return $this->responseSuccess($user, $request);
    }
}
