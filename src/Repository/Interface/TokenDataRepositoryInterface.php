<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\Entity\TokenInfo;

interface TokenDataRepositoryInterface
{
    public function getTokenInfo($token): TokenInfo;
}
