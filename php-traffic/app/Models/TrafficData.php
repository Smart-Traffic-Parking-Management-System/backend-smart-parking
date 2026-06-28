<?php

namespace App\Models;

use PDO;

class TrafficData
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO traffic_readings (zone_id, vehicle_density, avg_speed_kmh, incident_flag, source)
                VALUES (:zone_id, :vehicle_density, :avg_speed_kmh, :incident_flag, :source)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'zone_id'         => $data['zone_id'],
            'vehicle_density' => $data['vehicle_density'],
            'avg_speed_kmh'   => $data['avg_speed_kmh'],
            'incident_flag'   => $data['incident_flag'] ?? 0,
            'source'          => $data['source'] ?? 'iot',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function find(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM traffic_readings WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function getCurrentByZone(): array
    {
        $sql = "SELECT t1.*
                FROM traffic_readings t1
                INNER JOIN (
                    SELECT zone_id, MAX(recorded_at) AS max_recorded
                    FROM traffic_readings
                    GROUP BY zone_id
                ) t2 ON t1.zone_id = t2.zone_id AND t1.recorded_at = t2.max_recorded
                ORDER BY t1.zone_id";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function getHistory(?int $zoneId = null, ?string $date = null): array
    {
        $sql = "SELECT * FROM traffic_readings WHERE 1=1";
        $params = [];

        if ($zoneId !== null) {
            $sql .= " AND zone_id = :zone_id";
            $params['zone_id'] = $zoneId;
        }

        if ($date !== null) {
            $sql .= " AND DATE(recorded_at) = :date";
            $params['date'] = $date;
        }

        $sql .= " ORDER BY recorded_at DESC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
