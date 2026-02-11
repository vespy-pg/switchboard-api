<?php

namespace App\Repository;

use App\Entity\UserSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSession::class);
    }

    /**
     * Find an active session by token hash
     */
    public function findActiveSessionByTokenHash(string $tokenHash): ?UserSession
    {
        return $this->createQueryBuilder('s')
            ->where('s.tokenHash = :tokenHash')
            ->andWhere('s.removedAt IS NULL')
            ->andWhere('s.revokedAt IS NULL')
            ->andWhere('s.expiresAt > :now')
            ->setParameter('tokenHash', $tokenHash)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Revoke all sessions for a user
     */
    public function revokeAllUserSessions(string $userId): int
    {
        return $this->createQueryBuilder('s')
            ->update()
            ->set('s.revokedAt', ':now')
            ->where('s.userId = :userId')
            ->andWhere('s.removedAt IS NULL')
            ->andWhere('s.revokedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }
}
