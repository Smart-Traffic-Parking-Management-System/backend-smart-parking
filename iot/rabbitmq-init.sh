#!/bin/bash
echo "Waiting for RabbitMQ..."
sleep 5

BASE="http://localhost:15672/api"
AUTH="guest:guest"

# Buat exchange city.events
curl -s -u $AUTH -X PUT "$BASE/exchanges/%2F/city.events" \
  -H "Content-Type: application/json" \
  -d '{"type":"topic","durable":true}'
echo "Exchange city.events created"

# Buat 4 queue
for queue in traffic.new parking.update anomaly.alert report.submitted; do
  curl -s -u $AUTH -X PUT "$BASE/queues/%2F/$queue" \
    -H "Content-Type: application/json" \
    -d '{"durable":true}'

  curl -s -u $AUTH -X POST "$BASE/bindings/%2F/e/city.events/q/$queue" \
    -H "Content-Type: application/json" \
    -d "{\"routing_key\":\"$queue\"}"

  echo "Queue $queue created and bound"
done

echo "RabbitMQ setup complete!"