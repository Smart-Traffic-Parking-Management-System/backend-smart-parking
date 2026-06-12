<?php
namespace App\Models;

class Reservation {
    private $db;
    
    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }
    
    public function create($citizenId, $slotId) {
        $stmt = $this->db->prepare("
            INSERT INTO parking_reservations (citizen_id, slot_id, status) 
            VALUES (?, ?, 'reserved')
        ");
        $stmt->execute([$citizenId, $slotId]);
        return $this->db->lastInsertId();
    }
    
    public function checkin($reservationId) {
        $stmt = $this->db->prepare("
            UPDATE parking_reservations 
            SET checked_in_at = NOW(), status = 'active' 
            WHERE id = ?
        ");
        return $stmt->execute([$reservationId]);
    }
    
    public function checkout($reservationId) {
        $stmt = $this->db->prepare("
            UPDATE parking_reservations 
            SET checked_out_at = NOW(), 
                duration_minutes = TIMESTAMPDIFF(MINUTE, checked_in_at, NOW()),
                status = 'completed'
            WHERE id = ?
        ");
        return $stmt->execute([$reservationId]);
    }
    
    public function getActiveByCitizen($citizenId) {
        $stmt = $this->db->prepare("
            SELECT r.*, s.slot_number, pz.name as zone_name
            FROM parking_reservations r
            JOIN parking_slots s ON r.slot_id = s.id
            JOIN parking_zones pz ON s.parking_zone_id = pz.id
            WHERE r.citizen_id = ? AND r.status IN ('reserved', 'active')
            ORDER BY r.reserved_at DESC
        ");
        $stmt->execute([$citizenId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getHistory($citizenId) {
        $stmt = $this->db->prepare("
            SELECT r.*, s.slot_number, pz.name as zone_name
            FROM parking_reservations r
            JOIN parking_slots s ON r.slot_id = s.id
            JOIN parking_zones pz ON s.parking_zone_id = pz.id
            WHERE r.citizen_id = ? AND r.status = 'completed'
            ORDER BY r.checked_out_at DESC
            LIMIT 20
        ");
        $stmt->execute([$citizenId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}