import pandas as pd, numpy as np, random, math
import os # Tambahkan modul os di sini
from datetime import datetime, timedelta

rows = []
zones = ['zone1', 'zone2', 'zone3', 'zone4']
start = datetime(2024, 1, 1)

for i in range(5000):
    ts = start + timedelta(minutes=30 * i)
    hour = ts.hour
    dow = ts.weekday()
    for zone in zones:
        # Pola rush hour realistis
        base = 30 + 50 * math.sin((hour - 7) * math.pi / 12) if 7 <= hour <= 19 else 10
        density = max(0, base + random.gauss(0, 8))
        rows.append({
            'timestamp': ts.isoformat(),
            'hour': hour,
            'day_of_week': dow,
            'weather_code': random.randint(0, 3),
            'prev_density': max(0, density - random.gauss(0, 5)),
            'location': zone,
            'vehicle_density': density,
        })

df = pd.DataFrame(rows)

os.makedirs('data', exist_ok=True)

df.to_csv('data/traffic_history.csv', index=False)
print(f"Generated {len(df)} rows")