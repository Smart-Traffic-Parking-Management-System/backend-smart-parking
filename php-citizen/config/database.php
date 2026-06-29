<?php
// config/database.php
function getDBConnection() {
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: 3306;
    $dbname = getenv('DB_NAME') ?: 'smartcity';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASSWORD') ?: '';
    
    try {
        $pdo = new \PDO(
            "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
            $user,
            $pass,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    } catch (\PDOException $e) {
        die(json_encode(['status' => 'error', 'code' => 500, 'message' => 'Database connection failed: ' . $e->getMessage()]));
    }
}