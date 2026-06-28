<?php
namespace App\Services;

class RabbitMQPublisher {
    private $connection;
    private $channel;
    
    public function __construct() {
        $host = getenv('RABBITMQ_HOST') ?: 'localhost';
        $port = getenv('RABBITMQ_PORT') ?: 5672;
        $user = getenv('RABBITMQ_USER') ?: 'guest';
        $pass = getenv('RABBITMQ_PASS') ?: 'guest';
        
        try {
            $this->connection = new \AMQPStreamConnection($host, $port, $user, $pass);
            $this->channel = $this->connection->channel();
            
            // Declare exchange
            $this->channel->exchange_declare('city.events', 'topic', false, true, false);
        } catch (\Exception $e) {
            error_log("RabbitMQ connection failed: " . $e->getMessage());
        }
    }
    
    public function publish($routingKey, $message) {
        if (!$this->channel) {
            error_log("RabbitMQ channel not available");
            return false;
        }
        
        $msg = new \AMQPMessage(json_encode($message), [
            'content_type' => 'application/json',
            'delivery_mode' => 2  # persistent
        ]);
        
        $this->channel->basic_publish($msg, 'city.events', $routingKey);
        return true;
    }
    
    public function __destruct() {
        if ($this->channel) {
            $this->channel->close();
        }
        if ($this->connection) {
            $this->connection->close();
        }
    }
}