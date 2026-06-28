from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import List
from datetime import datetime, timezone
import joblib
import pandas as pd
import numpy as np

app = FastAPI(title="Smart City ML Service")

try:
    artifacts = joblib.load('models/smartcity_models.pkl')
    rf_model = artifacts['rf_model']
    scaler_traffic = artifacts['scaler_traffic']
    le_loc = artifacts['le_loc']
    
    gb_model = artifacts['gb_model']
    scaler_park = artifacts['scaler_park']
    le_zone = artifacts['le_zone']
    
    iso_forest = artifacts['iso_forest']
except Exception as e:
    raise RuntimeError("Gagal memuat model. Pastikan train_models.py sudah dijalankan.")

class TrafficInput(BaseModel):
    hour: int
    day_of_week: int
    weather_code: int
    prev_density: float
    location: str

class ParkingInput(BaseModel):
    hour: int
    day_of_week: int
    zone_id: str
    historical_avg_occupancy: float

class AnomalyInput(BaseModel):
    sensor_value: float
    timestamp_hour: int
    rolling_mean_1h: float
    z_score: float

class BatchTrafficInput(BaseModel):
    inputs: List[TrafficInput]

def format_response(data: dict, message: str, code: int = 200):
    return {
        "status": "success" if code == 200 else "error",
        "code": code,
        "data": data,
        "message": message,
        "timestamp": datetime.now(timezone.utc).isoformat()[:-6] + "Z",
        "service": "python-ml-service"
    }

@app.get("/health")
def health_check():
    return format_response({"models_loaded": True}, "ML Service is healthy and ready")

@app.post("/predict/traffic")
def predict_traffic(data: TrafficInput):
    try:
        loc_enc = le_loc.transform([data.location])[0]
        input_df = pd.DataFrame(
            [[data.hour, data.day_of_week, data.weather_code, data.prev_density, loc_enc]],
            columns=['hour', 'day_of_week', 'weather_code', 'prev_density', 'location_enc']
        )
        input_scaled = scaler_traffic.transform(input_df)
        
        pred_density = float(rf_model.predict(input_scaled)[0])
        
        if pred_density > 80:
            level = "Padat"
        elif pred_density > 40:
            level = "Sedang"
        else:
            level = "Lancar"
            
        result = {
            "predicted_density": round(pred_density, 2),
            "congestion_level": level
        }
        return format_response(result, "Traffic prediction successful")
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/predict/parking")
def predict_parking(data: ParkingInput):
    try:
        zone_enc = le_zone.transform([data.zone_id])[0]
        input_df = pd.DataFrame(
            [[data.hour, data.day_of_week, zone_enc, data.historical_avg_occupancy]],
            columns=['hour', 'day_of_week', 'zone_id_enc', 'historical_avg_occupancy']
        )
        input_scaled = scaler_park.transform(input_df)
        
        occ_rate = float(gb_model.predict(input_scaled)[0])
        occ_rate = min(1.0, max(0.0, occ_rate)) 
        
        if occ_rate >= 0.90:
            label = "Penuh"
        elif occ_rate >= 0.75:
            label = "Hampir Penuh"
        else:
            label = "Tersedia"
            
        result = {
            "occupancy_rate": round(occ_rate, 2),
            "availability_label": label
        }
        return format_response(result, "Parking forecast successful")
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/detect/anomaly")
def detect_anomaly(data: AnomalyInput):
    try:
        input_df = pd.DataFrame(
            [[data.sensor_value, data.timestamp_hour, data.rolling_mean_1h, data.z_score]],
            columns=['sensor_value', 'timestamp_hour', 'rolling_mean_1h', 'z_score']
        )
        
        prediction = iso_forest.predict(input_df)[0]
        is_anomaly = bool(prediction == -1)
        
        score = abs(data.z_score) if is_anomaly else 0.0
        if score > 3.0:
            severity = "Kritis"
        elif score > 2.0:
            severity = "Peringatan"
        else:
            severity = "Normal"
            
        result = {
            "is_anomaly": is_anomaly,
            "anomaly_score": round(score, 2),
            "severity": severity
        }
        return format_response(result, "Anomaly detection successful")
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.get("/model/feature-importance")
def get_feature_importance():
    try:
        rf_importance = dict(zip(['hour', 'day_of_week', 'weather_code', 'prev_density', 'location_enc'], rf_model.feature_importances_))
        gb_importance = dict(zip(['hour', 'day_of_week', 'zone_id_enc', 'historical_avg_occupancy'], gb_model.feature_importances_))
        
        data = {
            "traffic_model_rf": rf_importance,
            "parking_model_gb": gb_importance,
            "anomaly_model_iso": "Model Unsupervised tidak menyediakan bobot fitur standar"
        }
        return format_response(data, "Feature importance retrieved")
    except Exception as e:
         raise HTTPException(status_code=500, detail=str(e))

@app.post("/predict/batch")
def predict_batch(data: BatchTrafficInput):
    try:
        results = []
        for item in data.inputs:
            loc_enc = le_loc.transform([item.location])[0]
            input_df = pd.DataFrame(
                [[item.hour, item.day_of_week, item.weather_code, item.prev_density, loc_enc]],
                columns=['hour', 'day_of_week', 'weather_code', 'prev_density', 'location_enc']
            )
            input_scaled = scaler_traffic.transform(input_df)
            pred_density = float(rf_model.predict(input_scaled)[0])
            
            if pred_density > 80: level = "Padat"
            elif pred_density > 40: level = "Sedang"
            else: level = "Lancar"
                
            results.append({
                "location": item.location,
                "hour": item.hour,
                "predicted_density": round(pred_density, 2),
                "congestion_level": level
            })
        return format_response({"batch_results": results}, "Batch prediction successful")
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))