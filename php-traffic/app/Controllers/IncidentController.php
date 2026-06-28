<?php

namespace App\Controllers;

use App\Models\Incident;
use PDO;

class IncidentController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function store(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($input['zone_id']) || empty($input['type'])) {
            $this->response('error', 422, [], 'zone_id dan type wajib diisi');
            return;
        }

        $model = new Incident($this->db);
        $id = $model->create($input);
        $record = $model->find($id);

        $this->response('success', 201, $record, 'Insiden berhasil dilaporkan');
    }

    public function index(): void
    {
        $model = new Incident($this->db);
        $data = $model->getActive();

        $this->response('success', 200, $data, 'Daftar insiden aktif berhasil diambil');
    }

    public function resolve(int $id): void
    {
        $model = new Incident($this->db);
        $record = $model->find($id);

        if (!$record) {
            $this->response('error', 404, [], 'Insiden tidak ditemukan');
            return;
        }

        $model->resolve($id);
        $updated = $model->find($id);

        $this->response('success', 200, $updated, 'Insiden berhasil diselesaikan');
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
