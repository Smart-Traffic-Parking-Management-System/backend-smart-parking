<?php
namespace App\Models;

class Notification {
    private $db;
    
    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }
    
    public function create($citizenId, $title, $body, $type = 'info') {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (citizen_id, title, body, type) 
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$citizenId, $title, $body, $type]);
    }
    
    public function getByCitizen($citizenId) {
        $stmt = $this->db->prepare("
            SELECT * FROM notifications 
            WHERE citizen_id = ? 
            ORDER BY created_at DESC 
            LIMIT 50
        ");
        $stmt->execute([$citizenId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function markAsRead($id, $citizenId) {
        $stmt = $this->db->prepare("
            UPDATE notifications SET is_read = 1 
            WHERE id = ? AND citizen_id = ?
        ");
        return $stmt->execute([$id, $citizenId]);
    }
    
    public function createForZone($zoneId, $title, $body, $type = 'warning') {
        // Get all citizens in this zone
        $stmt = $this->db->prepare("SELECT id FROM citizens WHERE zone_id = ?");
        $stmt->execute([$zoneId]);
        $citizens = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($citizens as $citizen) {
            $this->create($citizen['id'], $title, $body, $type);
        }
        return count($citizens);
    }
}