<?php

namespace App\Models;

use PDO;

class Road
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function all(): array
    {
        $stmt = $this->db->query("SELECT * FROM zones ORDER BY id");
        return $stmt->fetchAll();
    }
}
