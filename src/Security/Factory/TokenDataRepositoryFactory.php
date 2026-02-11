<?php

namespace App\Security\Factory;

use App\Repository\Interface\TokenDataRepositoryInterface;
use App\Repository\TokenDataRepositoryApi;
use App\Repository\TokenDataRepositoryCache;
use Psr\Log\LoggerInterface;

class TokenDataRepositoryFactory
{
    public static function getRepository(
        bool $useCache,
        $cache,
        LoggerInterface $logger,
        $authDomain,
        $authTokenValidationEndpoint,
        $authMiddlewareToken,
        $useHttp,
        $db
    ): TokenDataRepositoryInterface {
        if ($useCache) {
            return new TokenDataRepositoryCache(
                $cache,
                $logger,
                $db,
                $authDomain,
                $authTokenValidationEndpoint,
                $authMiddlewareToken,
                $useHttp
            );
        }

        return new TokenDataRepositoryApi(
            $db,
            $authDomain,
            $authTokenValidationEndpoint,
            $authMiddlewareToken,
            $useHttp
        );
    }
}
