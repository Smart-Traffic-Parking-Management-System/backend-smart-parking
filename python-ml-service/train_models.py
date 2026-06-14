import pandas as pd
import os
import joblib
from sklearn.ensemble import RandomForestRegressor, GradientBoostingRegressor, IsolationForest
from sklearn.preprocessing import StandardScaler, LabelEncoder

# Kita memastikan folder models tersedia
os.makedirs('models', exist_ok=True)

print("Memuat dataset dari folder data/ ...")
df_traffic = pd.read_csv('data/traffic_history.csv')
df_parking = pd.read_csv('data/parking_history.csv')

print("Training Model 1: Traffic Predictor...")
le_loc = LabelEncoder()
df_traffic['location_enc'] = le_loc.fit_transform(df_traffic['location'])
X_traffic = df_traffic[['hour', 'day_of_week', 'weather_code', 'prev_density', 'location_enc']]
y_traffic = df_traffic['vehicle_density']
scaler_traffic = StandardScaler()
X_traffic_scaled = scaler_traffic.fit_transform(X_traffic)

rf_model = RandomForestRegressor(n_estimators=100, random_state=42)
rf_model.fit(X_traffic_scaled, y_traffic)

print("Training Model 2: Parking Forecast...")
le_zone = LabelEncoder()
df_parking['zone_id_enc'] = le_zone.fit_transform(df_parking['zone_id'])
X_park = df_parking[['hour', 'day_of_week', 'zone_id_enc', 'historical_avg_occupancy']]
y_park = df_parking['occupancy_rate']
scaler_park = StandardScaler()
X_park_scaled = scaler_park.fit_transform(X_park)

gb_model = GradientBoostingRegressor(n_estimators=100, random_state=42)
gb_model.fit(X_park_scaled, y_park)

print("Training Model 3: Anomaly Detector...")
df_traffic['sensor_value'] = df_traffic['vehicle_density']
df_traffic['timestamp_hour'] = df_traffic['hour']

df_traffic['rolling_mean_1h'] = df_traffic.groupby('location')['sensor_value'].transform(lambda x: x.rolling(window=2, min_periods=1).mean())

df_traffic['z_score'] = df_traffic.groupby('location')['sensor_value'].transform(lambda x: (x - x.mean()) / x.std())
df_traffic['z_score'] = df_traffic['z_score'].fillna(0) # Mengatasi nilai kosong

X_anomaly = df_traffic[['sensor_value', 'timestamp_hour', 'rolling_mean_1h', 'z_score']]
iso_forest = IsolationForest(contamination=0.05, random_state=42)
iso_forest.fit(X_anomaly)

print("Menyimpan seluruh model dan encoder ke dalam .pkl ...")
# Encoder dan scaler juga disimpan agar api dapat mengkonversikan input teks mejadi format yang sesuai
artifacts = {
    'rf_model': rf_model,
    'scaler_traffic': scaler_traffic,
    'le_loc': le_loc,
    'gb_model': gb_model,
    'scaler_park': scaler_park,
    'le_zone': le_zone,
    'iso_forest': iso_forest
}

joblib.dump(artifacts, 'models/smartcity_models.pkl')
print("✅ Selesai! Semua model berhasil disimpan ke models/smartcity_models.pkl")