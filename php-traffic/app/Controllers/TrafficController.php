<?php

namespace App\Controllers;

use App\Models\TrafficData;
use App\Services\RabbitMQPublisher;
use App\Validators\TrafficValidator;
use PDO;

class TrafficController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function store(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $errors = TrafficValidator::validateReading($input);
        if (!empty($errors)) {
            $this->response('error', 422, ['errors' => $errors], 'Validasi gagal');
            return;
        }

        $model = new TrafficData($this->db);
        $id = $model->create($input);
        $record = $model->find($id);

        $publisher = new RabbitMQPublisher();
        $publisher->publish('traffic.new', [
            'id'        => (int) $record['id'],
            'location'  => (int) $record['zone_id'],
            'speed_kmh' => (float) $record['avg_speed_kmh'],
            'density'   => (float) $record['vehicle_density'],
            'timestamp' => $record['recorded_at'],
        ]);

        $this->response('success', 201, $record, 'Traffic reading berhasil disimpan');
    }

    public function current(): void
    {
        $model = new TrafficData($this->db);
        $data = $model->getCurrentByZone();

        $this->response('success', 200, $data, 'Data traffic terbaru per zona');
    }

    public function history(): void
    {
        $zoneId = $_GET['zone_id'] ?? null;
        $date = $_GET['date'] ?? null;

        $model = new TrafficData($this->db);
        $data = $model->getHistory(
            $zoneId !== null ? (int) $zoneId : null,
            $date
        );

        $this->response('success', 200, $data, 'Riwayat traffic berhasil diambil');
    }

    private function response(string $status, int $code, $data, string $message): void
    {
        http_response_code($code);
        echo json_encode([
            'status'    => $status,
            'code'      => $code,
            'data'      => $data,
            'message'   => $message,
            'timestamp' => date('c'),
            'service'   => 'traffic-service',
        ]);
        exit;
    }
}
