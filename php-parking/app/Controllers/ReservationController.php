<?php
namespace App\Controllers;

require_once __DIR__ . '/../../config/database.php';

class ReservationController {
    public function reserve() {
        $data = json_decode(file_get_contents('php://input'), true);
        $citizenId = $data['citizen_id'] ?? 1;
        $slotId = $data['slot_id'];
        
        $db = getDBConnection();
        $slotModel = new \App\Models\ParkingSlot($db);
        
        // Cek slot tersedia
        $slot = $slotModel->getById($slotId);
        if (!$slot || $slot['status'] !== 'available') {
            return ['status' => 'error', 'code' => 400, 'message' => 'Slot tidak tersedia'];
        }
        
        // Lock slot
        $slotModel->updateStatus($slotId, 'reserved');
        
        // Buat reservasi
        $reserveModel = new \App\Models\Reservation($db);
        $reservationId = $reserveModel->create($citizenId, $slotId);
        
        // Publish update ke RabbitMQ
        $this->publishParkingUpdate($slotId, 'reserved');
        
        return [
            'status' => 'success',
            'code' => 201,
            'data' => ['reservation_id' => $reservationId],
            'message' => 'Reservasi berhasil',
            'timestamp' => date('c'),
            'service' => 'parking-service'
        ];
    }
    
    public function checkin($reservationId) {
        $db = getDBConnection();
        $reserveModel = new \App\Models\Reservation($db);
        $reserveModel->checkin($reservationId);
        
        // Update slot status
        // (ambil slot_id dari reservasi dulu)
        
        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'Check-in berhasil',
            'timestamp' => date('c'),
            'service' => 'parking-service'
        ];
    }
    
    public function checkout($reservationId) {
        $db = getDBConnection();
        $reserveModel = new \App\Models\Reservation($db);
        $reserveModel->checkout($reservationId);
        
        // Update slot status jadi available
        // publish update
        
        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'Check-out berhasil',
            'timestamp' => date('c'),
            'service' => 'parking-service'
        ];
    }
    
    public function getHistory() {
        $citizenId = $_GET['citizen_id'] ?? 1;
        $db = getDBConnection();
        $model = new \App\Models\Reservation($db);
        $history = $model->getHistory($citizenId);
        
        return [
            'status' => 'success',
            'code' => 200,
            'data' => $history,
            'timestamp' => date('c'),
            'service' => 'parking-service'
        ];
    }
    
    private function publishParkingUpdate($slotId, $status) {
        try {
        require_once __DIR__ . '/../../vendor/autoload.php';
        $publisher = new \App\Services\RabbitMQPublisher();
        $publisher->publish('parking.update', [
            'slot_id'   => $slotId,
            'status'    => $status,
            'timestamp' => date('c'),
        ]);
    } catch (\Exception $e) {
        error_log("RabbitMQ publish failed: " . $e->getMessage());
        }
    }
}