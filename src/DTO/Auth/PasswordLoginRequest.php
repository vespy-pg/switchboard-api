<?php

namespace App\DTO\Auth;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class PasswordLoginRequest
{
    #[Groups(['auth:login'])]
    #[Assert\NotBlank(groups: ['auth:login'])]
    #[Assert\Email(groups: ['auth:login'])]
    public ?string $email = null;

    #[Groups(['auth:login'])]
    #[Assert\NotBlank(groups: ['auth:login'])]
    public ?string $password = null;
}
