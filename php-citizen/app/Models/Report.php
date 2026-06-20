<?php
namespace App\Models;

class Report {
    private $db;
    
    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }
    
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO reports (citizen_id, category, description, zone_id, latitude, longitude) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['citizen_id'], $data['category'], $data['description'],
            $data['zone_id'], $data['latitude'], $data['longitude']
        ]);
        return $this->db->lastInsertId();
    }
    
    public function getList($filters = []) {
        $sql = "SELECT r.*, c.name as citizen_name, z.name as zone_name 
                FROM reports r
                LEFT JOIN citizens c ON r.citizen_id = c.id
                LEFT JOIN zones z ON r.zone_id = z.id
                WHERE 1=1";
        $params = [];
        
        if (isset($filters['status'])) {
            $sql .= " AND r.status = ?";
            $params[] = $filters['status'];
        }
        if (isset($filters['zone_id'])) {
            $sql .= " AND r.zone_id = ?";
            $params[] = $filters['zone_id'];
        }
        
        $sql .= " ORDER BY r.created_at DESC LIMIT 100";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function updateStatus($id, $status) {
        $stmt = $this->db->prepare("UPDATE reports SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }
}