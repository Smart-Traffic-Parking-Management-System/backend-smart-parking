<?php
namespace App\Models;

class ParkingSlot {
    private $db;
    
    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }
    
    public function getZones() {
        $stmt = $this->db->query("SELECT * FROM parking_zones");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getSlots($zoneId = null) {
        $sql = "SELECT s.*, pz.name as zone_name, pz.type 
                FROM parking_slots s
                JOIN parking_zones pz ON s.parking_zone_id = pz.id";
        if ($zoneId) {
            $sql .= " WHERE pz.id = " . intval($zoneId);
        }
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getAvailableSlots($zoneId = null) {
        $sql = "SELECT s.*, pz.name as zone_name 
                FROM parking_slots s
                JOIN parking_zones pz ON s.parking_zone_id = pz.id
                WHERE s.status = 'available'";
        if ($zoneId) {
            $sql .= " AND pz.id = " . intval($zoneId);
        }
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function updateStatus($slotId, $status) {
        $stmt = $this->db->prepare("UPDATE parking_slots SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $slotId]);
    }
    
    public function getById($slotId) {
        $stmt = $this->db->prepare("SELECT * FROM parking_slots WHERE id = ?");
        $stmt->execute([$slotId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}