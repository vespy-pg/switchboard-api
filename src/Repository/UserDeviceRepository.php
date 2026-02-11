<?php

namespace App\Repository;

use App\Entity\UserDevice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserDevice>
 */
class UserDeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserDevice::class);
    }

    /**
     * Find active devices for a user
     */
    public function findActiveDevices(string $userId): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.user = :userId')
            ->andWhere('d.revokedAt IS NULL')
            ->andWhere('d.expiresAt > :now')
            ->setParameter('userId', $userId)
            ->setParameter('now', new \DateTime())
            ->orderBy('d.lastUsedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find device by remember token
     */
    public function findByRememberToken(string $token): ?UserDevice
    {
        return $this->createQueryBuilder('d')
            ->where('d.rememberToken = :token')
            ->andWhere('d.revokedAt IS NULL')
            ->andWhere('d.expiresAt > :now')
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Revoke all devices for a user except the current one
     */
    public function revokeAllExcept(string $userId, ?string $exceptToken = null): int
    {
        $qb = $this->createQueryBuilder('d')
            ->update()
            ->set('d.revokedAt', ':now')
            ->where('d.user = :userId')
            ->andWhere('d.revokedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('now', new \DateTime());

        if ($exceptToken) {
            $qb->andWhere('d.rememberToken != :token')
               ->setParameter('token', $exceptToken);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * Revoke a specific device
     */
    public function revokeDevice(string $deviceId): bool
    {
        $device = $this->find($deviceId);

        if (!$device) {
            return false;
        }

        $device->setRevokedAt(new \DateTime());
        $this->getEntityManager()->flush();

        return true;
    }

    /**
     * Clean up expired and revoked tokens older than specified days
     */
    public function cleanupExpiredTokens(int $daysOld = 90): int
    {
        $cutoffDate = new \DateTime("-{$daysOld} days");

        return $this->createQueryBuilder('d')
            ->delete()
            ->where('d.expiresAt < :now OR d.revokedAt IS NOT NULL')
            ->andWhere('d.createdAt < :cutoff')
            ->setParameter('now', new \DateTime())
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();
    }

    /**
     * Update device last used timestamp
     */
    public function updateLastUsed(UserDevice $device, string $ipAddress): void
    {
        $device->setLastUsedAt(new \DateTime());
        $device->setLastIpAddress($ipAddress);
        $this->getEntityManager()->flush();
    }

    /**
     * Mark device as trusted
     */
    public function markAsTrusted(string $deviceId): bool
    {
        $device = $this->find($deviceId);

        if (!$device) {
            return false;
        }

        $device->setIsTrusted('true');
        $this->getEntityManager()->flush();

        return true;
    }

    /**
     * Count active devices for a user
     */
    public function countActiveDevices(string $userId): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.user = :userId')
            ->andWhere('d.revokedAt IS NULL')
            ->andWhere('d.expiresAt > :now')
            ->setParameter('userId', $userId)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
