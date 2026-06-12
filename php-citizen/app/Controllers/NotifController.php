<?php
namespace App\Controllers;

require_once __DIR__ . '/../../config/database.php';

class NotifController {
    public function getNotifications() {
        // Ambil citizen_id dari JWT
        $citizenId = $_GET['citizen_id'] ?? 1;
        
        $db = getDBConnection();
        $model = new \App\Models\Notification($db);
        $notifications = $model->getByCitizen($citizenId);
        
        return [
            'status' => 'success',
            'code' => 200,
            'data' => $notifications,
            'timestamp' => date('c'),
            'service' => 'citizen-service'
        ];
    }
    
    public function markAsRead($id) {
        $citizenId = $_GET['citizen_id'] ?? 1;
        
        $db = getDBConnection();
        $model = new \App\Models\Notification($db);
        $model->markAsRead($id, $citizenId);
        
        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'Notifikasi ditandai sudah dibaca',
            'timestamp' => date('c'),
            'service' => 'citizen-service'
        ];
    }
}