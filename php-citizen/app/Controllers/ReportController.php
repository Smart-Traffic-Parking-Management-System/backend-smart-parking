<?php
namespace App\Controllers;

require_once __DIR__ . '/../../config/database.php';

class ReportController {
    public function submit() {
        $data = json_decode(file_get_contents('php://input'), true);
        $headers = getallheaders();
        
        // Ambil citizen_id dari JWT (simplifikasi, nanti dari token)
        // Untuk sementara, hardcode dulu untuk testing
        $citizenId = $data['citizen_id'] ?? 1;
        
        $db = getDBConnection();
        $reportModel = new \App\Models\Report($db);
        
        $reportData = [
            'citizen_id' => $citizenId,
            'category' => $data['category'],
            'description' => $data['description'] ?? '',
            'zone_id' => $data['zone_id'],
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null
        ];
        
        $reportId = $reportModel->create($reportData);
        
        // Publish ke RabbitMQ
        $this->publishToRabbitMQ([
            'report_id' => $reportId,
            'citizen_id' => $citizenId,
            'zone_id' => $data['zone_id'],
            'category' => $data['category'],
            'timestamp' => date('c')
        ]);
        
        return [
            'status' => 'success',
            'code' => 201,
            'data' => ['report_id' => $reportId],
            'message' => 'Laporan berhasil disubmit',
            'timestamp' => date('c'),
            'service' => 'citizen-service'
        ];
    }
    
    public function getList() {
        $db = getDBConnection();
        $model = new \App\Models\Report($db);
        $filters = $_GET;
        $reports = $model->getList($filters);
        
        return [
            'status' => 'success',
            'code' => 200,
            'data' => $reports,
            'timestamp' => date('c'),
            'service' => 'citizen-service'
        ];
    }
    
    private function publishToRabbitMQ($message) {
    require_once __DIR__ . '/../Services/RabbitMQPublisher.php';
    $publisher = new \App\Services\RabbitMQPublisher();
    $publisher->publish('report.submitted', $message);
    }   
}