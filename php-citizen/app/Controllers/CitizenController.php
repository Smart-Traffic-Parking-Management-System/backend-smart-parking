<?php
namespace App\Controllers;

require_once __DIR__ . '/../../config/database.php';

class CitizenController {
    public function register() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validasi sederhana
        if (empty($data['nik']) || empty($data['email']) || empty($data['password'])) {
            return ['status' => 'error', 'code' => 422, 'message' => 'NIK, email, dan password wajib diisi'];
        }
        
        $db = getDBConnection();
        $model = new \App\Models\Citizen($db);
        
        // Cek email sudah terdaftar?
        $existing = $model->findByEmail($data['email']);
        if ($existing) {
            return ['status' => 'error', 'code' => 409, 'message' => 'Email sudah terdaftar'];
        }
        
        $citizenId = $model->create($data);
        
        return [
            'status' => 'success',
            'code' => 201,
            'data' => ['id' => $citizenId],
            'message' => 'Warga berhasil didaftarkan',
            'timestamp' => date('c'),
            'service' => 'citizen-service'
        ];
    }
    
    public function getProfile($id) {
        // Ambil JWT dari header (nanti diimplementasikan)
        $db = getDBConnection();
        $model = new \App\Models\Citizen($db);
        $citizen = $model->findById($id);
        
        if (!$citizen) {
            return ['status' => 'error', 'code' => 404, 'message' => 'Warga tidak ditemukan'];
        }
        
        unset($citizen['password_hash']);
        
        return [
            'status' => 'success',
            'code' => 200,
            'data' => $citizen,
            'timestamp' => date('c'),
            'service' => 'citizen-service'
        ];
    }
}