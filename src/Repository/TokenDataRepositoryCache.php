<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TokenInfo;
use App\Repository\Wrapper\PDOWrapper;
use App\Repository\Interface\TokenDataRepositoryInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

class TokenDataRepositoryCache extends TokenDataRepositoryApi implements TokenDataRepositoryInterface
{
    protected CacheItemPoolInterface $cache;
    private LoggerInterface $logger;

    public function __construct(
        CacheItemPoolInterface $cache,
        LoggerInterface $logger,
        PDOWrapper $db,
        string $authDomain,
        string $authTokenValidationEndpoint,
        string $authMiddlewareToken,
        bool $useHttp
    ) {
        $this->cache = $cache;
        $this->logger = $logger;
        parent::__construct($db, $authDomain, $authTokenValidationEndpoint, $authMiddlewareToken, $useHttp);
    }

    /**
     * @param $token
     *
     * @return TokenInfo
     * @throws InvalidArgumentException
     */
    public function getTokenInfo($token): TokenInfo
    {
        try {
            $cacheItem = $this->cache->getItem($token);
            if ($cacheItem->isHit()) {
                $data = json_decode($cacheItem->get(), true);

                $expiry = new \DateTime($data['expires_at']);
                $now = new \DateTime('now');

                if ($expiry > $now) {
                    return parent::arrayToEntity($data);
                }

                // If token is past the expiry time - token is expired, delete from cache
                $this->cache->deleteItem($token);
            }
            $tokenInfo = parent::getTokenInfo($token);
            $this->cacheTokenInfo($cacheItem, $tokenInfo);
            return $tokenInfo;
        } catch (\Exception $e) {
            $this->logger->error($e);
            return parent::getTokenInfo($token);
        }
    }

    private function cacheTokenInfo(CacheItemInterface $cacheItem, TokenInfo $tokenInfo)
    {
        $data = json_encode($tokenInfo->toArray());
        $cacheItem->set($data);
        $cacheItem->expiresAfter(60 * 10); //10 minutes
        $this->cache->save($cacheItem);
    }
}
