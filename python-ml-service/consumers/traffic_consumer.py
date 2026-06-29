import anomaly_publisher 
import pika
import json
import joblib
import pandas as pd
import os
from datetime import datetime

print("Memuat model Anomaly Detector...")
try:
    artifacts = joblib.load('models/smartcity_models.pkl')
    iso_forest = artifacts['iso_forest']
except Exception as e:
    print(f"Error memuat model: {e}")
    exit(1)

# Konfigurasi RabbitMQ (menggunakan default env, sedangkan penggunaan localhost untuk testing lokal))
RABBITMQ_HOST = os.getenv('RABBITMQ_HOST', 'localhost')
RABBITMQ_PORT = int(os.getenv('RABBITMQ_PORT', 5672))
RABBITMQ_USER = os.getenv('RABBITMQ_USER', 'guest')
RABBITMQ_PASS = os.getenv('RABBITMQ_PASS', 'guest')
history_data = {}

def callback(ch, method, props, body):
    try:
        event = json.loads(body)
        density = float(event.get('density', 0))
        zone_id = event.get('location')
        
        dt_obj = datetime.fromisoformat(event['timestamp'].replace('Z', '+00:00'))
        hour = dt_obj.hour
        
        if zone_id not in history_data:
            history_data[zone_id] = []
            
        history_data[zone_id].append(density)
        if len(history_data[zone_id]) > 10:
            history_data[zone_id].pop(0)
            
        recent_values = history_data[zone_id]
        
        # Rolling mean 1 jam (rata-rata dari 2 data terakhir jika interval 30 menit)
        rolling_mean_1h = sum(recent_values[-2:]) / min(len(recent_values), 2)
        
        # Z-score perhitungan dinamis
        if len(recent_values) > 1:
            mean_val = sum(recent_values) / len(recent_values)
            variance = sum((x - mean_val) ** 2 for x in recent_values) / len(recent_values)
            std_dev = variance ** 0.5 if variance > 0 else 1.0
            z_score = (density - mean_val) / std_dev
        else:
            z_score = 0.0

       
        input_df = pd.DataFrame(
            [[density, hour, rolling_mean_1h, z_score]], 
            columns=['sensor_value', 'timestamp_hour', 'rolling_mean_1h', 'z_score']
        )
        prediction = iso_forest.predict(input_df)[0]
        is_anomaly = bool(prediction == -1)
        
        print(f"[x] Menerima data Zona: {zone_id} | Kepadatan: {density} | Anomali: {is_anomaly}")
        

        if is_anomaly:
            score = abs(z_score)
            severity = "critical" if score > 3.0 else "warning"
            
            alert_payload = {
                'zone_id': event.get('location'),
                'sensor_value': density,
                'anomaly_score': round(score, 2),
                'severity': severity,
                'timestamp': event.get('timestamp'),
            }
            
            anomaly_publisher.publish_alert(ch, alert_payload)
            
        ch.basic_ack(delivery_tag=method.delivery_tag)
        
    except Exception as e:
        print(f"Error memproses pesan: {e}")

try:
    credentials = pika.PlainCredentials(RABBITMQ_USER, RABBITMQ_PASS)
    connection = pika.BlockingConnection(pika.ConnectionParameters(
        host=RABBITMQ_HOST, 
        port=RABBITMQ_PORT,
        credentials=credentials
    ))
    channel = connection.channel()
    
    channel.exchange_declare(exchange='city.events', exchange_type='topic', durable=True)
    channel.queue_declare(queue='traffic.new', durable=True)
    channel.queue_bind(exchange='city.events', queue='traffic.new', routing_key='traffic.new')
    
    channel.queue_declare(queue='anomaly.alert', durable=True)
    channel.queue_bind(exchange='city.events', queue='anomaly.alert', routing_key='anomaly.alert')
    
    print(' [*] Menunggu pesan di queue "traffic.new". Tekan CTRL+C untuk keluar')
    
    channel.basic_qos(prefetch_count=1)
    channel.basic_consume(queue='traffic.new', on_message_callback=callback)
    
    channel.start_consuming()
except Exception as e:
    print(f"Gagal terhubung ke RabbitMQ: {e}")
    print("CATATAN: Consumer Pastikan RabbitMQ dinyalakan")