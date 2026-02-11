<?php

namespace App\DTO\Auth;

use App\Entity\User;
use App\Validator\Constraints\EntityUniqueConstraint;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class RegisterRequest
{
    #[Groups(['auth:register'])]
    #[Assert\NotBlank(groups: ['auth:register'])]
    #[Assert\Email(groups: ['auth:register'])]
    #[EntityUniqueConstraint(
        entityClass: User::class,
        field: 'email',
        message: 'This email is already registered.',
        groups:['auth:register']
    )]
    public ?string $email = null;

    #[Groups(['auth:register'])]
    #[Assert\NotBlank(groups: ['auth:register'])]
    #[Assert\Length(min: 8, max: 4096, groups: ['auth:register'])]
    public ?string $password = null;

    #[Groups(['auth:register'])]
    #[Assert\Length(max: 100, groups: ['auth:register'])]
    #[Assert\Regex(
        pattern: '/\p{L}/u',
        message: 'First name must contain at least one letter.',
        groups: ['auth:register']
    )]
    public ?string $firstName = null;
}
