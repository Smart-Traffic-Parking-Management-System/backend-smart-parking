<?php
// php-citizen/public/index.php - DENGAN PENYIMPANAN DATA

error_reporting(E_ALL);
ini_set('display_errors', 1);

// File penyimpanan data
$dataFile = __DIR__ . '/../data/citizens.json';

// Inisialisasi file jika belum ada
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode(['citizens' => [], 'next_id' => 1]));
}

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function loadData($file) {
    return json_decode(file_get_contents($file), true);
}

function saveData($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$uri = strtok($uri, '?');
$uri = rtrim($uri, '/');

// ============================================
// HEALTH CHECK
// ============================================
if ($uri === '/health' && $method === 'GET') {
    sendResponse([
        'status' => 'success',
        'code' => 200,
        'message' => 'Citizen service OK',
        'timestamp' => date('c'),
        'service' => 'citizen-service'
    ]);
}

// ============================================
// REGISTER CITIZEN (POST) - MENYIMPAN DATA
// ============================================
elseif ($uri === '/api/citizens' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validasi
    if (empty($input['nik']) || empty($input['email']) || empty($input['password'])) {
        sendResponse([
            'status' => 'error',
            'code' => 422,
            'message' => 'NIK, email, dan password wajib diisi',
            'timestamp' => date('c'),
            'service' => 'citizen-service'
        ], 422);
    }
    
    // Load data existing
    $data = loadData($dataFile);
    
    // Cek apakah email sudah terdaftar
    foreach ($data['citizens'] as $citizen) {
        if ($citizen['email'] === $input['email']) {
            sendResponse([
                'status' => 'error',
                'code' => 409,
                'message' => 'Email sudah terdaftar',
                'timestamp' => date('c'),
                'service' => 'citizen-service'
            ], 409);
        }
    }
    
    // Buat citizen baru
    $newCitizen = [
        'id' => $data['next_id'],
        'nik' => $input['nik'],
        'name' => $input['name'],
        'email' => $input['email'],
        'password' => password_hash($input['password'], PASSWORD_DEFAULT),
        'phone' => $input['phone'] ?? '',
        'zone_id' => (int)$input['zone_id'],
        'role' => 'citizen',
        'created_at' => date('c')
    ];
    
    $data['citizens'][] = $newCitizen;
    $data['next_id']++;
    saveData($dataFile, $data);
    
    // Response tanpa password
    unset($newCitizen['password']);
    
    sendResponse([
        'status' => 'success',
        'code' => 201,
        'data' => $newCitizen,
        'message' => 'Warga berhasil didaftarkan',
        'timestamp' => date('c'),
        'service' => 'citizen-service'
    ], 201);
}

// ============================================
// GET CITIZEN PROFILE (BERDASARKAN ID YANG BENAR)
// ============================================
elseif (preg_match('/^\/api\/citizens\/(\d+)$/', $uri, $matches) && $method === 'GET') {
    $id = (int)$matches[1];
    
    // Load data
    $data = loadData($dataFile);
    
    // Cari citizen dengan ID tersebut
    $found = null;
    foreach ($data['citizens'] as $citizen) {
        if ($citizen['id'] === $id) {
            $found = $citizen;
            unset($found['password']); // Hapus password dari response
            break;
        }
    }
    
    if ($found) {
        sendResponse([
            'status' => 'success',
            'code' => 200,
            'data' => $found,
            'timestamp' => date('c'),
            'service' => 'citizen-service'
        ]);
    } else {
        sendResponse([
            'status' => 'error',
            'code' => 404,
            'message' => "Warga dengan ID {$id} tidak ditemukan",
            'timestamp' => date('c'),
            'service' => 'citizen-service'
        ], 404);
    }
}

// ============================================
// SUBMIT REPORT
// ============================================
elseif ($uri === '/api/reports' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $reportFile = __DIR__ . '/../data/reports.json';
    if (!file_exists($reportFile)) {
        file_put_contents($reportFile, json_encode(['reports' => [], 'next_id' => 1]));
    }
    
    $data = json_decode(file_get_contents($reportFile), true);
    
    $newReport = [
        'id' => $data['next_id'],
        'citizen_id' => $input['citizen_id'],
        'category' => $input['category'],
        'description' => $input['description'],
        'zone_id' => $input['zone_id'],
        'latitude' => $input['latitude'] ?? null,
        'longitude' => $input['longitude'] ?? null,
        'status' => 'reported',
        'created_at' => date('c')
    ];
    
    $data['reports'][] = $newReport;
    $data['next_id']++;
    file_put_contents($reportFile, json_encode($data, JSON_PRETTY_PRINT));
    
    sendResponse([
        'status' => 'success',
        'code' => 201,
        'data' => ['report_id' => $newReport['id']],
        'message' => 'Laporan berhasil disubmit',
        'timestamp' => date('c'),
        'service' => 'citizen-service'
    ], 201);
}

// ============================================
// GET REPORTS LIST
// ============================================
elseif ($uri === '/api/reports' && $method === 'GET') {
    $reportFile = __DIR__ . '/../data/reports.json';
    if (!file_exists($reportFile)) {
        sendResponse([
            'status' => 'success',
            'code' => 200,
            'data' => [],
            'timestamp' => date('c'),
            'service' => 'citizen-service'
        ]);
    }
    
    $data = json_decode(file_get_contents($reportFile), true);
    
    sendResponse([
        'status' => 'success',
        'code' => 200,
        'data' => $data['reports'],
        'timestamp' => date('c'),
        'service' => 'citizen-service'
    ]);
}

// ============================================
// GET NOTIFICATIONS
// ============================================
elseif ($uri === '/api/notifications' && $method === 'GET') {
    $citizenId = $_GET['citizen_id'] ?? 1;
    
    sendResponse([
        'status' => 'success',
        'code' => 200,
        'data' => [
            [
                'id' => 1,
                'citizen_id' => (int)$citizenId,
                'title' => 'Selamat Datang!',
                'body' => 'Selamat bergabung di Smart Traffic System',
                'type' => 'info',
                'is_read' => 0,
                'created_at' => date('c')
            ]
        ],
        'timestamp' => date('c'),
        'service' => 'citizen-service'
    ]);
}

// ============================================
// MARK NOTIFICATION AS READ
// ============================================
elseif (preg_match('/^\/api\/notifications\/(\d+)\/read$/', $uri, $matches) && $method === 'PATCH') {
    $id = $matches[1];
    sendResponse([
        'status' => 'success',
        'code' => 200,
        'message' => "Notifikasi ID {$id} ditandai sudah dibaca",
        'timestamp' => date('c'),
        'service' => 'citizen-service'
    ]);
}

// ============================================
// 404 NOT FOUND
// ============================================
else {
    sendResponse([
        'status' => 'error',
        'code' => 404,
        'message' => "Endpoint not found: {$uri}",
        'timestamp' => date('c'),
        'service' => 'citizen-service'
    ], 404);
}
?>