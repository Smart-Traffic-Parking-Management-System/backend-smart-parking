<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher
{
    private string $host;
    private string $port;
    private string $user;
    private string $pass;

    public function __construct()
    {
        $this->host = $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq';
        $this->port = $_ENV['RABBITMQ_PORT'] ?? '5672';
        $this->user = $_ENV['RABBITMQ_USER'] ?? 'guest';
        $this->pass = $_ENV['RABBITMQ_PASS'] ?? 'guest';
    }

    public function publish(string $queue, array $payload): void
    {
        try {
            $connection = new AMQPStreamConnection($this->host, $this->port, $this->user, $this->pass);
            $channel = $connection->channel();

            $channel->queue_declare($queue, false, true, false, false);

            $message = new AMQPMessage(json_encode($payload), [
                'content_type'  => 'application/json',
                'delivery_mode' => 2,
            ]);

            $channel->basic_publish($message, '', $queue);

            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            error_log("RabbitMQ publish error: " . $e->getMessage());
        }
    }
}
