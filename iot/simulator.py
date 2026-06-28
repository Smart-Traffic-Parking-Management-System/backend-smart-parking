import time
import math
import random
import json
import os
import paho.mqtt.client as mqtt

MQTT_HOST = os.getenv("MQTT_HOST", "localhost")
MQTT_PORT = int(os.getenv("MQTT_PORT", 1883))
MQTT_USER = os.getenv("MQTT_USER", "iot_device")
MQTT_PASS = os.getenv("MQTT_PASS", "SmartCity@MQTT#2026") #

ZONES = ["zone1", "zone2", "zone3", "zone4", "zone5"]

def simulate_traffic(hour):
    if 7 <= hour <= 19:
        base = 30 + 50 * math.sin((hour - 7) * math.pi / 12)
    else:
        base = 10
    density = max(0, base + random.gauss(0, 8))
    speed = max(5, 60 - density * 0.5 + random.gauss(0, 3))
    return round(density, 2), round(speed, 2)

def simulate_parking(hour):
    occupied = random.randint(0, 100)
    return occupied

def main():
    client = mqtt.Client()
    client.username_pw_set(MQTT_USER, MQTT_PASS)
    client.connect(MQTT_HOST, MQTT_PORT, 60)

    client.loop_start()

    while True:
        hour = time.localtime().tm_hour

        for zone in ZONES:
            density, speed = simulate_traffic(hour)
            traffic_payload = {
                "zone": zone,
                "vehicle_density": density,
                "avg_speed_kmh": speed,
                "timestamp": time.strftime("%Y-%m-%d %H:%M:%S"),
            }
            client.publish(f"city/{zone}/traffic", json.dumps(traffic_payload))
            print(f"Published traffic {zone}: {traffic_payload}")

            occupied = simulate_parking(hour)
            parking_payload = {
                "zone": zone,
                "occupied_slots": occupied,
                "timestamp": time.strftime("%Y-%m-%d %H:%M:%S"),
            }
            client.publish(f"city/{zone}/parking", json.dumps(parking_payload))
            print(f"Published parking {zone}: {parking_payload}")

        time.sleep(30)

if __name__ == "__main__":
    main()
