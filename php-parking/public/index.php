<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

$reservationsFile = __DIR__ . '/../data/reservations.json';
$slotsFile = __DIR__ . '/../data/slots.json';

if (!file_exists(__DIR__ . '/../data')) {
    mkdir(__DIR__ . '/../data', 0777, true);
}
if (!file_exists($reservationsFile)) {
    file_put_contents($reservationsFile, json_encode(['reservations' => [], 'next_id' => 1]));
}
if (!file_exists($slotsFile)) {
    $slots = [
        ['id' => 1, 'slot_number' => 'A01', 'status' => 'available', 'parking_zone_id' => 1],
        ['id' => 2, 'slot_number' => 'A02', 'status' => 'available', 'parking_zone_id' => 1],
        ['id' => 3, 'slot_number' => 'A03', 'status' => 'available', 'parking_zone_id' => 1],
        ['id' => 4, 'slot_number' => 'B01', 'status' => 'available', 'parking_zone_id' => 2],
        ['id' => 5, 'slot_number' => 'B02', 'status' => 'available', 'parking_zone_id' => 2],
        ['id' => 6, 'slot_number' => 'B03', 'status' => 'available', 'parking_zone_id' => 2],
    ];
    file_put_contents($slotsFile, json_encode($slots, JSON_PRETTY_PRINT));
}

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function loadReservations() {
    global $reservationsFile;
    return json_decode(file_get_contents($reservationsFile), true);
}

function saveReservations($data) {
    global $reservationsFile;
    file_put_contents($reservationsFile, json_encode($data, JSON_PRETTY_PRINT));
}

function loadSlots() {
    global $slotsFile;
    return json_decode(file_get_contents($slotsFile), true);
}

function saveSlots($slots) {
    global $slotsFile;
    file_put_contents($slotsFile, json_encode($slots, JSON_PRETTY_PRINT));
}

function updateSlotStatus($slotId, $status) {
    $slots = loadSlots();
    foreach ($slots as &$slot) {
        if ($slot['id'] == $slotId) {
            $slot['status'] = $status;
            break;
        }
    }
    saveSlots($slots);
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
    sendResponse(['status'=>'success','code'=>200,'message'=>'Parking service OK','timestamp'=>date('c'),'service'=>'parking-service']);
}

// POST PARKING READINGS (IoT gateway forward)
elseif ($uri === '/api/parking/readings' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $slotId = isset($input['slot_id']) ? (int)$input['slot_id'] : null;
    $status = $input['status'] ?? 'occupied';
    $timestamp = $input['timestamp'] ?? date('c');

    if ($slotId) {
        updateSlotStatus($slotId, $status);
    }

    sendResponse([
        'status' => 'success',
        'code' => 201,
        'data' => [
            'slot_id' => $slotId,
            'status' => $status,
            'timestamp' => $timestamp,
        ],
        'message' => 'Parking reading berhasil diterima',
        'timestamp' => date('c'),
        'service' => 'parking-service'
    ], 201);
}

// GET PARKING ZONES
elseif ($uri === '/api/parking/zones' && $method === 'GET') {
    sendResponse(['status'=>'success','code'=>200,'data'=>[
        ['id'=>1,'name'=>'Parkir Pusat Kota','zone_id'=>1,'total_slots'=>50,'type'=>'umum'],
        ['id'=>2,'name'=>'Parkir Mal','zone_id'=>4,'total_slots'=>100,'type'=>'umum'],
        ['id'=>3,'name'=>'Parkir Stadion','zone_id'=>2,'total_slots'=>200,'type'=>'umum']
    ],'timestamp'=>date('c'),'service'=>'parking-service']);
}

// GET PARKING SLOTS
elseif ($uri === '/api/parking/slots' && $method === 'GET') {
    $slots = loadSlots();
    $zoneId = $_GET['zone_id'] ?? null;
    if ($zoneId) {
        $slots = array_values(array_filter($slots, function($slot) use ($zoneId) {
            if ($zoneId == 1) return $slot['id'] <= 3;
            if ($zoneId == 2) return $slot['id'] >= 4;
            return true;
        }));
    }
    $total = count($slots);
    $available = count(array_filter($slots, fn($s) => $s['status'] === 'available'));
    sendResponse(['status'=>'success','code'=>200,'data'=>[
        'total_slots'=>$total,
        'available_slots'=>$available,
        'occupancy_rate'=>$total > 0 ? round(($total-$available)/$total*100,2) : 0,
        'slots'=>$slots
    ],'timestamp'=>date('c'),'service'=>'parking-service']);
}

// RESERVE PARKING SLOT
elseif ($uri === '/api/parking/reserve' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $citizenId = $input['citizen_id'];
    $slotId = $input['slot_id'];

    $slots = loadSlots();
    $slotAvailable = false;
    foreach ($slots as $slot) {
        if ($slot['id'] == $slotId && $slot['status'] === 'available') {
            $slotAvailable = true;
            break;
        }
    }

    if (!$slotAvailable) {
        sendResponse(['status'=>'error','code'=>400,'message'=>'Slot parkir tidak tersedia','timestamp'=>date('c'),'service'=>'parking-service'], 400);
    }

    updateSlotStatus($slotId, 'reserved');

    $reservations = loadReservations();
    $newReservation = [
        'id' => $reservations['next_id'],
        'citizen_id' => $citizenId,
        'slot_id' => $slotId,
        'status' => 'reserved',
        'reserved_at' => date('c'),
        'checked_in_at' => null,
        'checked_out_at' => null,
        'duration_minutes' => null
    ];
    $reservations['reservations'][] = $newReservation;
    $reservations['next_id']++;
    saveReservations($reservations);

    publishToRabbitMQ('parking.update', [
        'slot_id'    => $slotId,
        'status'     => 'reserved',
        'citizen_id' => $citizenId,
        'timestamp'  => date('c')
    ]);

    sendResponse(['status'=>'success','code'=>201,'data'=>['reservation_id'=>$newReservation['id']],'message'=>'Reservasi berhasil','timestamp'=>date('c'),'service'=>'parking-service'], 201);
}

// CHECK-IN
elseif (preg_match('/^\/api\/parking\/checkin\/(\d+)$/', $uri, $matches) && $method === 'PATCH') {
    $reservationId = (int)$matches[1];
    $reservations = loadReservations();
    $found = false;

    foreach ($reservations['reservations'] as &$res) {
        if ($res['id'] == $reservationId) {
            $res['status'] = 'active';
            $res['checked_in_at'] = date('c');
            $found = true;
            updateSlotStatus($res['slot_id'], 'occupied');
            break;
        }
    }
    saveReservations($reservations);

    if ($found) {
        sendResponse(['status'=>'success','code'=>200,'message'=>'Check-in berhasil','timestamp'=>date('c'),'service'=>'parking-service']);
    } else {
        sendResponse(['status'=>'error','code'=>404,'message'=>'Reservasi tidak ditemukan','timestamp'=>date('c'),'service'=>'parking-service'], 404);
    }
}

// CHECK-OUT
elseif (preg_match('/^\/api\/parking\/checkout\/(\d+)$/', $uri, $matches) && $method === 'PATCH') {
    $reservationId = (int)$matches[1];
    $reservations = loadReservations();
    $found = false;
    $slotId = null;

    foreach ($reservations['reservations'] as &$res) {
        if ($res['id'] == $reservationId) {
            $res['status'] = 'completed';
            $res['checked_out_at'] = date('c');
            if ($res['checked_in_at']) {
                $checkin = new DateTime($res['checked_in_at']);
                $checkout = new DateTime($res['checked_out_at']);
                $res['duration_minutes'] = $checkin->diff($checkout)->i + ($checkin->diff($checkout)->h * 60);
            }
            $slotId = $res['slot_id'];
            $found = true;
            break;
        }
    }
    saveReservations($reservations);

    if ($found) {
        updateSlotStatus($slotId, 'available');
        publishToRabbitMQ('parking.update', [
            'slot_id'   => $slotId,
            'status'    => 'available',
            'timestamp' => date('c')
        ]);
        sendResponse(['status'=>'success','code'=>200,'message'=>'Check-out berhasil','timestamp'=>date('c'),'service'=>'parking-service']);
    } else {
        sendResponse(['status'=>'error','code'=>404,'message'=>'Reservasi tidak ditemukan','timestamp'=>date('c'),'service'=>'parking-service'], 404);
    }
}

// GET PARKING HISTORY
elseif ($uri === '/api/parking/history' && $method === 'GET') {
    $citizenId = (int)($_GET['citizen_id'] ?? 0);
    if ($citizenId == 0) {
        sendResponse(['status'=>'error','code'=>400,'message'=>'citizen_id wajib diisi','timestamp'=>date('c'),'service'=>'parking-service'], 400);
    }
    $reservations = loadReservations();
    $slots = loadSlots();
    $history = [];
    foreach ($reservations['reservations'] as $res) {
        if ($res['citizen_id'] == $citizenId) {
            $slotNumber = '';
            foreach ($slots as $slot) {
                if ($slot['id'] == $res['slot_id']) {
                    $slotNumber = $slot['slot_number'];
                    break;
                }
            }
            $history[] = [
                'id' => $res['id'],
                'slot_number' => $slotNumber,
                'status' => $res['status'] ?? 'unknown',
                'reserved_at' => $res['reserved_at'] ?? '',
                'checked_in_at' => $res['checked_in_at'] ?? null,
                'checked_out_at' => $res['checked_out_at'] ?? null,
                'duration_minutes' => $res['duration_minutes'] ?? null
            ];
        }
    }
    sendResponse(['status'=>'success','code'=>200,'data'=>array_reverse($history),'timestamp'=>date('c'),'service'=>'parking-service']);
}

// GET SINGLE PARKING SLOT
elseif (preg_match('/^\/api\/parking\/slots\/(\d+)$/', $uri, $matches) && $method === 'GET') {
    $slotId = (int)$matches[1];
    $slots = loadSlots();
    $found = null;
    foreach ($slots as $slot) {
        if ($slot['id'] == $slotId) {
            $found = $slot;
            break;
        }
    }
    if ($found) {
        sendResponse(['status'=>'success','code'=>200,'data'=>$found,'timestamp'=>date('c'),'service'=>'parking-service']);
    } else {
        sendResponse(['status'=>'error','code'=>404,'message'=>"Slot ID {$slotId} tidak ditemukan",'timestamp'=>date('c'),'service'=>'parking-service'], 404);
    }
}

// 404
else {
    sendResponse(['status'=>'error','code'=>404,'message'=>"Endpoint not found: {$uri}",'timestamp'=>date('c'),'service'=>'parking-service'], 404);
}
?>
