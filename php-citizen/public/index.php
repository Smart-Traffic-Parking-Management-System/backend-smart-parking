<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

$dataFile = __DIR__ . '/../data/citizens.json';
$reportFile = __DIR__ . '/../data/reports.json';
$notifFile = __DIR__ . '/../data/notifications.json';

if (!file_exists(__DIR__ . '/../data')) {
    mkdir(__DIR__ . '/../data', 0777, true);
}
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode(['citizens' => [], 'next_id' => 1]));
}
if (!file_exists($reportFile)) {
    file_put_contents($reportFile, json_encode(['reports' => [], 'next_id' => 1]));
}
if (!file_exists($notifFile)) {
    file_put_contents($notifFile, json_encode(['notifications' => [], 'next_id' => 1]));
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

function publishToRabbitMQ($routingKey, $message) {
    try {
        require_once '/var/www/html/vendor/autoload.php';
        $conn = new \PhpAmqpLib\Connection\AMQPStreamConnection(
            getenv('RABBITMQ_HOST') ?: 'rabbitmq',
            getenv('RABBITMQ_PORT') ?: 5672,
            getenv('RABBITMQ_USER') ?: 'guest',
            getenv('RABBITMQ_PASS') ?: 'guest'
        );
        $ch = $conn->channel();
        $ch->exchange_declare('city.events', 'topic', false, true, false);
        $msg = new \PhpAmqpLib\Message\AMQPMessage(
            json_encode($message),
            ['delivery_mode' => 2]
        );
        $ch->basic_publish($msg, 'city.events', $routingKey);
        $ch->close();
        $conn->close();
        error_log('RabbitMQ publish OK: ' . $routingKey);
    } catch (Exception $e) {
        error_log('RabbitMQ failed: ' . $e->getMessage());
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$uri = strtok($uri, '?');
$uri = rtrim($uri, '/');

// HEALTH CHECK
if ($uri === '/health' && $method === 'GET') {
    sendResponse(['status'=>'success','code'=>200,'message'=>'Citizen service OK','timestamp'=>date('c'),'service'=>'citizen-service']);
}

// REGISTER CITIZEN
elseif ($uri === '/api/citizens' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['nik']) || empty($input['email']) || empty($input['password'])) {
        sendResponse(['status'=>'error','code'=>422,'message'=>'NIK, email, dan password wajib diisi','timestamp'=>date('c'),'service'=>'citizen-service'], 422);
    }
    $data = loadData($dataFile);
    foreach ($data['citizens'] as $citizen) {
        if ($citizen['email'] === $input['email']) {
            sendResponse(['status'=>'error','code'=>409,'message'=>'Email sudah terdaftar','timestamp'=>date('c'),'service'=>'citizen-service'], 409);
        }
    }
    $newCitizen = [
        'id' => $data['next_id'],
        'nik' => $input['nik'],
        'name' => $input['name'],
        'email' => $input['email'],
        'password' => password_hash($input['password'], PASSWORD_DEFAULT),
        'phone' => $input['phone'] ?? '',
        'zone_id' => (int)($input['zone_id'] ?? 1),
        'role' => 'citizen',
        'created_at' => date('c')
    ];
    $data['citizens'][] = $newCitizen;
    $data['next_id']++;
    saveData($dataFile, $data);
    unset($newCitizen['password']);
    sendResponse(['status'=>'success','code'=>201,'data'=>$newCitizen,'message'=>'Warga berhasil didaftarkan','timestamp'=>date('c'),'service'=>'citizen-service'], 201);
}

// GET CITIZEN PROFILE
elseif (preg_match('/^\/api\/citizens\/(\d+)$/', $uri, $matches) && $method === 'GET') {
    $id = (int)$matches[1];
    $data = loadData($dataFile);
    $found = null;
    foreach ($data['citizens'] as $citizen) {
        if ($citizen['id'] === $id) {
            $found = $citizen;
            unset($found['password']);
            break;
        }
    }
    if ($found) {
        sendResponse(['status'=>'success','code'=>200,'data'=>$found,'timestamp'=>date('c'),'service'=>'citizen-service']);
    } else {
        sendResponse(['status'=>'error','code'=>404,'message'=>"Warga ID {$id} tidak ditemukan",'timestamp'=>date('c'),'service'=>'citizen-service'], 404);
    }
}

// UPDATE CITIZEN PROFILE
elseif (preg_match('/^\/api\/citizens\/(\d+)$/', $uri, $matches) && $method === 'PUT') {
    $id = (int)$matches[1];
    $input = json_decode(file_get_contents('php://input'), true);
    $data = loadData($dataFile);
    $found = false;
    foreach ($data['citizens'] as &$citizen) {
        if ($citizen['id'] === $id) {
            if (!empty($input['name']))   $citizen['name']   = $input['name'];
            if (!empty($input['phone']))  $citizen['phone']  = $input['phone'];
            if (!empty($input['zone_id'])) $citizen['zone_id'] = (int)$input['zone_id'];
            $citizen['updated_at'] = date('c');
            $found = $citizen;
            unset($found['password']);
            break;
        }
    }
    if ($found) {
        saveData($dataFile, $data);
        sendResponse(['status'=>'success','code'=>200,'data'=>$found,'message'=>'Profil berhasil diupdate','timestamp'=>date('c'),'service'=>'citizen-service']);
    } else {
        sendResponse(['status'=>'error','code'=>404,'message'=>"Warga ID {$id} tidak ditemukan",'timestamp'=>date('c'),'service'=>'citizen-service'], 404);
    }
}

// SUBMIT REPORT
elseif ($uri === '/api/reports' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $data = loadData($reportFile);
    $newReport = [
        'id' => $data['next_id'],
        'citizen_id' => $input['citizen_id'],
        'category' => $input['category'],
        'description' => $input['description'] ?? '',
        'zone_id' => $input['zone_id'],
        'latitude' => $input['latitude'] ?? null,
        'longitude' => $input['longitude'] ?? null,
        'status' => 'reported',
        'created_at' => date('c')
    ];
    $data['reports'][] = $newReport;
    $data['next_id']++;
    saveData($reportFile, $data);
    publishToRabbitMQ('report.submitted', [
        'report_id'  => $newReport['id'],
        'citizen_id' => $newReport['citizen_id'],
        'zone_id'    => $newReport['zone_id'],
        'category'   => $newReport['category'],
        'timestamp'  => date('c')
    ]);
    sendResponse(['status'=>'success','code'=>201,'data'=>['report_id'=>$newReport['id']],'message'=>'Laporan berhasil disubmit','timestamp'=>date('c'),'service'=>'citizen-service'], 201);
}

// GET REPORTS LIST
elseif ($uri === '/api/reports' && $method === 'GET') {
    $data = loadData($reportFile);
    $reports = $data['reports'];
    if (!empty($_GET['status'])) {
        $reports = array_filter($reports, fn($r) => $r['status'] === $_GET['status']);
    }
    if (!empty($_GET['zone_id'])) {
        $reports = array_filter($reports, fn($r) => $r['zone_id'] == $_GET['zone_id']);
    }
    sendResponse(['status'=>'success','code'=>200,'data'=>array_values($reports),'timestamp'=>date('c'),'service'=>'citizen-service']);
}

// UPDATE REPORT STATUS
elseif (preg_match('/^\/api\/reports\/(\d+)\/status$/', $uri, $matches) && $method === 'PATCH') {
    $id = (int)$matches[1];
    $input = json_decode(file_get_contents('php://input'), true);
    $data = loadData($reportFile);
    $found = false;
    foreach ($data['reports'] as &$report) {
        if ($report['id'] === $id) {
            $report['status'] = $input['status'];
            $found = true;
            break;
        }
    }
    if ($found) {
        saveData($reportFile, $data);
        sendResponse(['status'=>'success','code'=>200,'message'=>'Status laporan diupdate','timestamp'=>date('c'),'service'=>'citizen-service']);
    } else {
        sendResponse(['status'=>'error','code'=>404,'message'=>'Laporan tidak ditemukan','timestamp'=>date('c'),'service'=>'citizen-service'], 404);
    }
}

// GET NOTIFICATIONS
elseif ($uri === '/api/notifications' && $method === 'GET') {
    $citizenId = (int)($_GET['citizen_id'] ?? 0);
    $data = loadData($notifFile);
    $notifs = array_filter($data['notifications'], fn($n) => $n['citizen_id'] == $citizenId);
    sendResponse(['status'=>'success','code'=>200,'data'=>array_values($notifs),'timestamp'=>date('c'),'service'=>'citizen-service']);
}

// MARK NOTIFICATION AS READ
elseif (preg_match('/^\/api\/notifications\/(\d+)\/read$/', $uri, $matches) && $method === 'PATCH') {
    $id = (int)$matches[1];
    $data = loadData($notifFile);
    foreach ($data['notifications'] as &$notif) {
        if ($notif['id'] === $id) {
            $notif['is_read'] = 1;
            break;
        }
    }
    saveData($notifFile, $data);
    sendResponse(['status'=>'success','code'=>200,'message'=>"Notifikasi ID {$id} ditandai sudah dibaca",'timestamp'=>date('c'),'service'=>'citizen-service']);
}

// 404
else {
    sendResponse(['status'=>'error','code'=>404,'message'=>"Endpoint not found: {$uri}",'timestamp'=>date('c'),'service'=>'citizen-service'], 404);
}
?>
