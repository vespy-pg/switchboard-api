<?php

declare(strict_types=1);

namespace App\Repository;

use App\Repository\Interface\UserRoleRepositoryInterface;
use App\Repository\Wrapper\PDOWrapper;
use PDO;

class UserRoleRepository implements UserRoleRepositoryInterface
{
    protected PDOWrapper $pdo;

    public function __construct(
        PDOWrapper $pdo
    ) {
        $this->pdo = $pdo;
    }

    public function getRolesByUsername(string $username): array
    {
        $query = <<<QUERY
SELECT r.role_name FROM rss.tbl_role r
LEFT JOIN rss.tbl_role_group rg on r.role_id = rg.role_id
LEFT JOIN rss.tbl_user_group ug on ug.group_id = rg.group_id
LEFT JOIN rss.tbl_user u on u.user_id = ug.user_id
WHERE u.username = :username
AND ug.user_id = u.user_id
QUERY;
        $qp = [
            'username' => $username,
        ];

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($qp);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
