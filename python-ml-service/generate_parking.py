import pandas as pd, random
from datetime import datetime, timedelta
import os

rows = []
zones = ['zone1', 'zone2', 'zone3', 'zone4']
start = datetime(2024, 1, 1)

for i in range(5000):
    ts = start + timedelta(minutes=30 * i)
    hour = ts.hour
    dow = ts.weekday()
    
    for zone in zones:
        # Logika: Parkiran padat saat jam kerja/aktivitas (08.00 - 17.00)
        base_occ = 0.85 if 8 <= hour <= 17 else 0.30
        
        # Tambahkan variasi acak agar realistis
        occ_rate = min(1.0, max(0.0, base_occ + random.gauss(0, 0.15)))
        
        # Buat histori okupansi yang berkorelasi dengan okupansi saat ini
        hist_occ = min(1.0, max(0.0, occ_rate - random.gauss(0, 0.05)))
        
        # Tentukan label teks berdasarkan aturan persentase
        if occ_rate >= 0.90:
            label = 'Penuh'
        elif occ_rate >= 0.75:
            label = 'Hampir Penuh'
        else:
            label = 'Tersedia'
            
        rows.append({
            'timestamp': ts.isoformat(),
            'hour': hour,
            'day_of_week': dow,
            'zone_id': zone,
            'historical_avg_occupancy': hist_occ,
            'occupancy_rate': occ_rate,
            'availability_label': label
        })

df_parking = pd.DataFrame(rows)

# Kita memasitkan untuk folder data tersedia
os.makedirs('data', exist_ok=True)
df_parking.to_csv('data/parking_history.csv', index=False)
print(f"Generated {len(df_parking)} rows for parking history.")