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

    public function update($id) {
        $raw  = json_decode(file_get_contents('php://input'), true);
        $data = array_change_key_case($raw ?? [], CASE_LOWER); // konsisten lowercase

        $allowed = ['name', 'email', 'phone', 'zone_id'];

        $updateData = [];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        // Handle alias: full_name → name
        if (isset($data['full_name']) && !isset($updateData['name'])) {
            $updateData['name'] = $data['full_name'];
        }

        $db    = getDBConnection();
        $model = new \App\Models\Citizen($db);
        $result = $model->update($id, $updateData);

        if (!$result) {
            return ['status' => 'error', 'code' => 500, 'message' => 'Gagal mengupdate profil'];
        }

        // Return updated data
        $citizen = $model->findById($id);
        unset($citizen['password_hash']);

        return [
            'status'    => 'success',
            'code'      => 200,
            'data'      => $citizen,
            'message'   => 'Profil berhasil diupdate',
            'timestamp' => date('c'),
            'service'   => 'citizen-service'
        ];
}
}