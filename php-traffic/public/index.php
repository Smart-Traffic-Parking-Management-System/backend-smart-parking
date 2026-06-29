<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\TrafficController;
use App\Controllers\RoadController;
use App\Controllers\IncidentController;

// load .env sederhana
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');

// koneksi PDO
$config = require __DIR__ . '/../config/database.php';
try {
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    $pdo = null;
    $dbError = $e->getMessage();
}

// fungsi response standar
function jsonResponse(string $status, int $code, $data, string $message): void
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

// ===== ROUTING =====

// health check
if ($uri === '/health' && $method === 'GET') {
    if ($pdo) {
        jsonResponse('success', 200, ['db' => 'connected'], 'Service healthy');
    } else {
        jsonResponse('error', 503, ['db' => 'disconnected'], 'Database connection failed');
    }
}

// traffic routes
if ($uri === '/api/traffic/readings' && $method === 'POST') {
    (new TrafficController($pdo))->store();
}

if ($uri === '/api/traffic/current' && $method === 'GET') {
    (new TrafficController($pdo))->current();
}

if ($uri === '/api/traffic/history' && $method === 'GET') {
    (new TrafficController($pdo))->history();
}

// roads
if ($uri === '/api/roads' && $method === 'GET') {
    (new RoadController($pdo))->index();
}

// incidents
if ($uri === '/api/incidents' && $method === 'POST') {
    (new IncidentController($pdo))->store();
}

if ($uri === '/api/incidents' && $method === 'GET') {
    (new IncidentController($pdo))->index();
}

// incidents/{id}/resolve
if (preg_match('#^/api/incidents/(\d+)/resolve$#', $uri, $matches) && $method === 'PATCH') {
    (new IncidentController($pdo))->resolve((int) $matches[1]);
}

// 404 default
jsonResponse('error', 404, [], 'Endpoint not found');
