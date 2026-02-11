<?php

namespace App\Repository\Wrapper;

class PDOWrapper extends \PDO
{
    public function __construct($dsn)
    {
        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];
        parent::__construct($dsn, null, null, $options);
    }
}
