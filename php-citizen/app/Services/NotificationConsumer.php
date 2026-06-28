#!/usr/bin/env php
<?php
namespace App\Services;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Models/Notification.php';
require_once __DIR__ . '/../../config/database.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class NotificationConsumer {
    private $connection;
    private $channel;
    private $notificationModel;
    
    public function __construct() {
        $host = getenv('RABBITMQ_HOST') ?: 'localhost';
        $port = getenv('RABBITMQ_PORT') ?: 5672;
        $user = getenv('RABBITMQ_USER') ?: 'guest';
        $pass = getenv('RABBITMQ_PASS') ?: 'guest';
        
        $this->connection = new AMQPStreamConnection($host, $port, $user, $pass);
        $this->channel = $this->connection->channel();
        
        $this->channel->exchange_declare('city.events', 'topic', false, true, false);
        
        // Declare queue
        $this->channel->queue_declare('anomaly.alert', false, true, false, false);
        
        // Bind queue ke exchange
        $this->channel->queue_bind('anomaly.alert', 'city.events', 'anomaly.alert');
        
        $db = getDBConnection();
        $this->notificationModel = new \App\Models\Notification($db);
    }
    
    public function start() {
        echo " [*] Waiting for anomaly alerts. Press CTRL+C to stop.\n";
        
        $callback = function($msg) {
            $data = json_decode($msg->body, true);
            echo " [x] Received anomaly alert: " . $msg->body . "\n";
            
            // Create notification for citizens in affected zone
            $zoneId = $data['zone_id'] ?? 1;
            $severity = $data['severity'] ?? 'warning';
            $title = "🚨 ANOMALI TERDETEKSI!";
            $body = "Terjadi anomali di zona " . $zoneId . ". Nilai sensor: " . ($data['sensor_value'] ?? 'N/A') . ". Severity: " . $severity;
            
            $type = ($severity === 'critical') ? 'critical' : 'warning';
            $count = $this->notificationModel->createForZone($zoneId, $title, $body, $type);
            
            echo " [✓] Notifikasi dibuat untuk $count warga di zona $zoneId\n";
            
            $msg->ack();
        };
        
        $this->channel->basic_consume('anomaly.alert', '', false, false, false, false, $callback);
        
        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }
    
    public function __destruct() {
        $this->channel->close();
        $this->connection->close();
    }
}

// Run consumer
$consumer = new NotificationConsumer();
$consumer->start();