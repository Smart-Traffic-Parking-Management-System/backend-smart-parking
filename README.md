# Smart Traffic & Parking Management System

Platform manajemen lalu lintas dan parkir berbasis microservice yang memonitor kepadatan lalu lintas dan ketersediaan parkir secara real-time.

## Arsitektur Sistem

- **API Gateway** (Express.js) — port 3000
- **OAuth Server** (Express.js) — port 3002
- **Citizen Service** (PHP 8.2) — port 8000
- **Traffic Service** (PHP 8.2) — port 8001
- **Parking Service** (PHP 8.2) — port 8002
- **Python ML Service** (FastAPI) — port 5000
- **RabbitMQ** (Message Broker) — port 5672, 15672
- **Mosquitto** (MQTT Broker) — port 1883
- **Prometheus** (Monitoring) — port 9090
- **Grafana** (Dashboard) — port 3001
- **MySQL** (Database) — port 3306

## Prerequisites

- Docker Desktop 24.x
- Docker Compose v2
- Git 2.x

## Setup Lokal

### 1. Clone repository
```bash
git clone https://github.com/Smart-Traffic-Parking-Management-System/backend-smart-parking.git
cd backend-smart-parking
```

### 2. Setup environment
```bash
cp .env.example .env
# Edit .env sesuai kebutuhan
```

### 3. Jalankan semua service
```bash
docker compose up -d --build
```

### 4. Cek status container
```bash
docker compose ps
```

### 5. Init database
```bash
docker exec -i smartcity-mysql mysql -u root -proot123 smartcity < database/schema.sql
docker exec -i smartcity-mysql mysql -u root -proot123 smartcity < database/seed.sql
```

### 6. Verifikasi sistem berjalan
```bash
curl http://localhost:3000/health
```

## Perintah Umum

```bash
# Jalankan semua service
make up

# Hentikan semua service
make down

# Lihat status container
make ps

# Lihat log service tertentu
make logs service=api-gateway

# Init database
make db-init

# Seed database
make db-seed
```

## Deploy ke Kubernetes

```bash
# Deploy semua manifest
make k8s-deploy

# Cek status pod
make k8s-status
```

## Monitoring

- **Grafana Dashboard:** http://localhost:3001
- **Prometheus:** http://localhost:9090
- **RabbitMQ Management:** http://localhost:15672

## Struktur Repository
backend-smart-parking/
├── database/          # Schema dan seed SQL
├── docs/              # Diagram arsitektur
├── express-gateway/   # API Gateway
├── iot/               # Konfigurasi MQTT
├── k8s/               # Kubernetes manifests
├── monitoring/        # Prometheus & Grafana
├── oauth-server/      # OAuth 2.0 Server
├── php-citizen/       # Citizen Service
├── php-parking/       # Parking Service
├── php-traffic/       # Traffic Service
├── docker-compose.yml
└── Makefile

## Tim Pengembang 
| Anggota | Tugas |
|---------|-------|
| Nadia | API Gateway + OAuth Server |
| Fakhry | Traffic Service + IoT |
| Ryan | Citizen Service + Parking Service |
| Reza | Python ML Service |
| Nafisa | DevOps + Infrastruktur |