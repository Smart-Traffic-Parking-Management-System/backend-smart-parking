FROM python:3.11-slim

# Untuk mengatur Healthcheck memakai curl
RUN apt-get update && apt-get install -y curl && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copy file requirements dan install dependensi ML (Pandas, Scikit-learn, FastAPI, Pika)
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Kita Copy semua file ke dalam Docker
COPY . .

# Bangun model ML sekali saat image dibuat
RUN python train_models.py

# Konfigurasi Healthcheck sesuai standar plan.md agar terdeteksi "Healthy" oleh Kubernetes/Docker
HEALTHCHECK --interval=30s --timeout=10s --retries=3 \
  CMD curl -f http://localhost:5000/health || exit 1

# Buka port 5000 agar FastAPI bisa diakses oleh API Gateway Anggota 1
EXPOSE 5000

# Baris ini menjalankan skrip consumer RabbitMQ di background (&), 
# dan menjalankan server FastAPI di foreground.
CMD sh -c "python -u consumers/traffic_consumer.py & \
           python -u consumers/parking_consumer.py & \
           uvicorn main:app --host 0.0.0.0 --port 5000"
