import pika
import json

def publish_alert(ch, alert_payload):
    ch.basic_publish(
        exchange='city.events',
        routing_key='anomaly.alert',
        body=json.dumps(alert_payload)
    )
    print(f"[!] ALERT PUBLISHED: {alert_payload['severity']} di {alert_payload['zone_id']}")