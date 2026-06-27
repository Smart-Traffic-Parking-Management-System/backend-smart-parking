<?php
namespace App\Controllers;

require_once __DIR__ . '/../../config/database.php';

class ParkingController {
    public function getZones() {
        $db = getDBConnection();
        $model = new \App\Models\ParkingSlot($db);
        $zones = $model->getZones();
        
        return [
            'status' => 'success',
            'code' => 200,
            'data' => $zones,
            'timestamp' => date('c'),
            'service' => 'parking-service'
        ];
    }
    
    public function getSlots($params) {
        $db = getDBConnection();
        $model = new \App\Models\ParkingSlot($db);
        $zoneId = $params['zone_id'] ?? null;
        $slots = $model->getSlots($zoneId);
        
        // Hitung statistik
        $total = count($slots);
        $available = count(array_filter($slots, fn($s) => $s['status'] === 'available'));
        
        return [
            'status' => 'success',
            'code' => 200,
            'data' => [
                'total_slots' => $total,
                'available_slots' => $available,
                'occupancy_rate' => round(($total - $available) / $total * 100, 2),
                'slots' => $slots
            ],
            'timestamp' => date('c'),
            'service' => 'parking-service'
        ];
    }
}