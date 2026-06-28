<?php

namespace App\Controllers;

use App\Models\Road;
use PDO;

class RoadController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function index(): void
    {
        $model = new Road($this->db);
        $data = $model->all();

        $this->response('success', 200, $data, 'Daftar zona/jalan berhasil diambil');
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
