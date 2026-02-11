<?php

namespace App\Repository;

use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public const ROLE_API = 1;
    public const ROLE_UI  = 2;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new InvalidArgumentException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPasswordHash($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Find active user by email (case-insensitive, normalized)
     */
    public function findActiveUserByEmail(string $email): ?User
    {
        $normalizedEmail = strtolower(trim($email));

        return $this->createQueryBuilder('u')
            ->where('u.email = :email')
            ->andWhere('u.removedAt IS NULL')
            ->setParameter('email', $normalizedEmail)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Load roles for a user (scope_ui or scope_api)
     */
    public function loadRoles(string $userId, int $flag): array
    {
        if (!in_array($flag, [self::ROLE_API, self::ROLE_UI], true)) {
            throw new InvalidArgumentException('Exactly one role flag must be provided.');
        }

        $scopeCondition = match ($flag) {
            self::ROLE_API => 'r.scope_api IS TRUE',
            self::ROLE_UI  => 'r.scope_ui IS TRUE',
        };

        $sql = <<<SQL
        SELECT DISTINCT r.role_code
        FROM app.tbl_user_group ug
        JOIN app.tbl_group g ON g.group_code = ug.group_code
        JOIN app.tbl_role_group rg ON rg.group_code = g.group_code
        JOIN app.tbl_role r ON r.role_code = rg.role_code
        WHERE ug.user_id = :user_id
          AND ug.is_active IS TRUE
          AND g.is_active IS TRUE
          AND {$scopeCondition}
        ORDER BY r.role_code
    SQL;

        $conn = $this->getEntityManager()->getConnection();
        $rows = $conn->executeQuery($sql, [
            'user_id' => $userId,
        ])->fetchAllAssociative();

        return array_column($rows, 'role_code');
    }

    /**
     * Check if user has a specific role (API scope)
     */
    public function hasRole(string $userId, string $roleCode): bool
    {
        $sql = '
            SELECT EXISTS (
                SELECT 1
                FROM app.tbl_user_group ug
                JOIN app.tbl_group g ON g.group_code = ug.group_code
                JOIN app.tbl_role_group rg ON rg.group_code = g.group_code
                JOIN app.tbl_role r ON r.role_code = rg.role_code
                WHERE ug.user_id = :user_id
                  AND ug.is_active IS TRUE
                  AND g.is_active IS TRUE
                  AND r.role_code = :role_code
                  AND r.scope_api IS TRUE
            ) AS has_permission
        ';

        $conn = $this->getEntityManager()->getConnection();
        $result = $conn->executeQuery($sql, [
            'user_id' => $userId,
            'role_code' => $roleCode,
        ]);

        return (bool) $result->fetchOne();
    }

    /**
     * @return array User[]
     */
    public function getAdmins(): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin(
                UserGroup::class,
                'ug',
                'WITH',
                'ug.user = u AND ug.group = :groupCode AND ug.isActive = true'
            )
            ->where('u.removedAt IS NULL')
            ->andWhere('u.isVerified = true')
            ->andWhere('u.emailVerifiedAt IS NOT NULL')
            ->andWhere('ug.isActive = true')
            ->setParameter('groupCode', Group::ADMIN)
            ->getQuery()
            ->getResult();
    }
}
