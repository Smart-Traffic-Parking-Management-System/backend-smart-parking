#!/bin/bash
echo "Waiting for RabbitMQ to be ready..."
until rabbitmqctl status > /dev/null 2>&1; do
  sleep 2
done
echo "RabbitMQ is ready. Setting up exchanges and queues..."

BASE="http://localhost:15672/api"
AUTH="guest:guest"

# Buat exchange city.events (topic, durable)
curl -s -u $AUTH -X PUT "$BASE/exchanges/%2F/city.events" \
  -H "Content-Type: application/json" \
  -d '{"type":"topic","durable":true}'

# Buat 4 queue yang dibutuhkan A3
for queue in traffic.new parking.update anomaly.alert report.submitted; do
  curl -s -u $AUTH -X PUT "$BASE/queues/%2F/$queue" \
    -H "Content-Type: application/json" \
    -d '{"durable":true}'
  
  # Bind queue ke exchange
  curl -s -u $AUTH -X POST "$BASE/bindings/%2F/e/city.events/q/$queue" \
    -H "Content-Type: application/json" \
    -d "{\"routing_key\":\"$queue\"}"
  
  echo "Queue $queue created and bound."
done

echo "RabbitMQ setup complete!"