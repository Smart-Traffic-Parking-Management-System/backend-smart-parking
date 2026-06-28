import pika
import json
import os

RABBITMQ_HOST = os.getenv('RABBITMQ_HOST', 'localhost')
RABBITMQ_PORT = int(os.getenv('RABBITMQ_PORT', 5672))

def callback(ch, method, props, body):
    try:
        event = json.loads(body)
        print(f"[x] Menerima update parkir dari Zona: {event.get('zone_id')} | Status: {event.get('status')}")
        # Logika ML akan dikembangkan di kesempatan berikutnya untuk memproses data parkir dengan sesuai
        ch.basic_ack(delivery_tag=method.delivery_tag)
    except Exception as e:
        print(f"Error: {e}")

try:
    connection = pika.BlockingConnection(pika.ConnectionParameters(host=RABBITMQ_HOST, port=RABBITMQ_PORT))
    channel = connection.channel()
    channel.exchange_declare(exchange='city.events', exchange_type='topic', durable=True)
    channel.queue_declare(queue='parking.update', durable=True)
    channel.queue_bind(exchange='city.events', queue='parking.update', routing_key='parking.update')
    
    print(' [*] Menunggu pesan di queue "parking.update".')
    channel.basic_consume(queue='parking.update', on_message_callback=callback)
    channel.start_consuming()
except Exception as e:
    print("Menunggu RabbitMQ menyala...")