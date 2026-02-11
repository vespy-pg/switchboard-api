<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\Entity\TokenInfo;

interface UserRoleRepositoryInterface
{
    public function getRolesByUsername(string $username): array;
}
