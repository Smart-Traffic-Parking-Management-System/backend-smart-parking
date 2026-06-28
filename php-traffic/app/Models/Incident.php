<?php

namespace App\Models;

use PDO;

class Incident
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO incidents (zone_id, type, severity, description, reported_by)
                VALUES (:zone_id, :type, :severity, :description, :reported_by)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'zone_id'     => $data['zone_id'],
            'type'        => $data['type'],
            'severity'    => $data['severity'] ?? 'sedang',
            'description' => $data['description'] ?? null,
            'reported_by' => $data['reported_by'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getActive(): array
    {
        $stmt = $this->db->query("SELECT * FROM incidents WHERE resolved_at IS NULL ORDER BY reported_at DESC");
        return $stmt->fetchAll();
    }

    public function find(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM incidents WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function resolve(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE incidents SET resolved_at = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
