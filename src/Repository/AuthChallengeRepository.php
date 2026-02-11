<?php

namespace App\Repository;

use App\Entity\AuthChallenge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AuthChallengeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthChallenge::class);
    }

    /**
     * Find the latest active challenge for a user and type
     */
    public function findLatestActiveChallenge(string $userId, string $challengeType): ?AuthChallenge
    {
        return $this->createQueryBuilder('c')
            ->where('c.userId = :userId')
            ->andWhere('c.challengeType = :challengeType')
            ->andWhere('c.usedAt IS NULL')
            ->andWhere('c.removedAt IS NULL')
            ->andWhere('c.expiresAt > :now')
            ->setParameter('userId', $userId)
            ->setParameter('challengeType', $challengeType)
            ->setParameter('now', new \DateTime())
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find active challenge by secret hash
     */
    public function findActiveChallengeByHash(string $secretHash): ?AuthChallenge
    {
        return $this->createQueryBuilder('c')
            ->where('c.secretHash = :secretHash')
            ->andWhere('c.usedAt IS NULL')
            ->andWhere('c.removedAt IS NULL')
            ->andWhere('c.expiresAt > :now')
            ->setParameter('secretHash', $secretHash)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Invalidate old challenges for a user and type
     */
    public function invalidateOldChallenges(string $userId, string $challengeType): int
    {
        return $this->createQueryBuilder('c')
            ->update()
            ->set('c.removedAt', ':now')
            ->where('c.userId = :userId')
            ->andWhere('c.challengeType = :challengeType')
            ->andWhere('c.removedAt IS NULL')
            ->andWhere('c.usedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('challengeType', $challengeType)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }
}
