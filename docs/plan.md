# Panduan Kolaborasi Proyek — Smart Traffic & Parking Management System

> **Mata Kuliah:** Pembangunan Perangkat Lunak Orientasi Berbasis Service  
> **Kelas:** Peminatan SE IF  
> **Tema Proyek:** Smart Traffic & Parking Management System  
> **Jumlah Anggota:** 5 Orang  
> **Deadline:** Sebelum demo Pertemuan 16 — tidak ada toleransi

---

## Daftar Isi

1. [Gambaran Umum Proyek](#1-gambaran-umum-proyek)
2. [Pembagian Tugas Per Anggota](#2-pembagian-tugas-per-anggota)
3. [Struktur Repository](#3-struktur-repository)
4. [Standar Kode & Konvensi Bersama](#4-standar-kode--konvensi-bersama)
5. [Panduan Setup Lokal](#5-panduan-setup-lokal)
6. [Panduan Per Anggota](#6-panduan-per-anggota)
   - [Anggota 1 — API Gateway + Auth](#anggota-1--api-gateway--oauth-20--jwt--rate-limiting)
   - [Anggota 2 — Traffic Service + IoT](#anggota-2--traffic-service--iot-layer)
   - [Anggota 3 — Parking + Citizen + RabbitMQ](#anggota-3--parking-service--citizen-service--rabbitmq)
   - [Anggota 4 — Python ML Service](#anggota-4--python-ml-service)
   - [Anggota 5 — DevOps + Infrastruktur](#anggota-5--devops--infrastruktur)
7. [Alur Kerja Git](#7-alur-kerja-git)
8. [Skenario Demo End-to-End](#8-skenario-demo-end-to-end)
9. [Skema Database Lengkap](#9-skema-database-lengkap)
10. [Spesifikasi API Endpoints](#10-spesifikasi-api-endpoints)
11. [Checklist Deliverables](#11-checklist-deliverables)
12. [Jadwal Kerja yang Disarankan](#12-jadwal-kerja-yang-disarankan)
13. [Aturan Server & Keamanan](#13-aturan-server--keamanan)

---

## 1. Gambaran Umum Proyek

### Deskripsi Singkat

**Smart Traffic & Parking Management System** adalah platform berbasis microservice yang memonitor kepadatan lalu lintas dan ketersediaan parkir secara real-time menggunakan sensor IoT, memprediksi kemacetan dengan Machine Learning, dan memberikan notifikasi otomatis kepada warga saat terjadi anomali.

### Arsitektur Sistem (6 Layer)

```
┌─────────────────────────────────────────────────────────────────┐
│  IoT LAYER                                                      │
│  Sensor Simulator (Python) → Mosquitto MQTT → Node-RED          │
└──────────────────────────┬──────────────────────────────────────┘
                           │ HTTP POST
┌──────────────────────────▼──────────────────────────────────────┐
│  GATEWAY LAYER                                                  │
│  API Gateway (Express.js :3000)  ←→  OAuth Server (:3002)       │
└──────┬──────────────┬──────────────┬────────────────────────────┘
       │              │              │
┌──────▼───┐  ┌───────▼──┐  ┌───────▼──────────────────────────┐
│ CITIZEN  │  │ TRAFFIC  │  │  PARKING SERVICE (PHP :8001)      │
│ SERVICE  │  │ SERVICE  │  │  (bagian dari traffic layer)      │
│ PHP :8000│  │ PHP :8001│  └───────────────────────────────────┘
└──────┬───┘  └───────┬──┘
       │              │ publish
┌──────▼──────────────▼──────────────────────────────────────────┐
│  MESSAGING LAYER                                               │
│  RabbitMQ (:5672) — exchange: city.events, city.commands       │
└──────────────────────┬──────────────────────────────────────────┘
                       │ consume
┌──────────────────────▼──────────────────────────────────────────┐
│  ML LAYER                                                       │
│  Python FastAPI (:5000) — 3 Model: Traffic Predictor,           │
│  Parking Occupancy Forecast, Anomaly Detector                   │
└─────────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────────┐
│  INFRA LAYER                                                    │
│  Docker Compose  →  Kubernetes (k8s/)                           │
│  Prometheus (:9090)  →  Grafana (:3001)                         │
└─────────────────────────────────────────────────────────────────┘
```

### Port yang Dialokasikan
| Service | Port | PIC |
|---|---:|---|
| API Gateway | 3000 | Anggota 1 |
| OAuth Server | 3002 | Anggota 1 |
| Citizen Service | 8000 | Anggota 3 |
| Traffic Service | 8001 | Anggota 2 |
| Parking / Env Service | 8002 | Anggota 3 |
| Python ML Service | 5000 | Anggota 4 |
| RabbitMQ Management UI | 15672 | Anggota 3 |
| Grafana | 3001 | Anggota 5 |
| Prometheus | 9090 | Anggota 5 |
| MQTT Broker (Mosquitto) | 1883 | Anggota 2 |
| Node-RED | 1880 | Anggota 2 |
---

## 2. Pembagian Tugas Per Anggota

### Ringkasan Tanggung Jawab

| ID | Layer | Tanggung Jawab Utama | Bobot (%) |
|---|---|---|---:|
| A1 | Gateway | API Gateway, OAuth2, JWT middleware, Rate limiting | 20 |
| A2 | IoT + Service | Traffic Service (PHP), MQTT simulator, Node-RED flows | 20 |
| A3 | Service + Messaging | Parking & Citizen services (PHP), RabbitMQ integration | 22 |
| A4 | ML | Python FastAPI, model training, RabbitMQ consumer | 15 |
| A5 | Infra | Docker Compose, Kubernetes manifests, monitoring | 22 |

> **Catatan:** Semua anggota berkontribusi pada dokumentasi (README, diagram arsitektur, Postman Collection) dan demo presentasi.

---

## 3. Struktur Repository

```
smart-traffic-platform/             ← root repository (GitHub Organization)
│
├── express-gateway/                # [A1] API Gateway + Rate Limiting
│   ├── src/
│   │   ├── index.js
│   │   ├── routes/
│   │   │   └── proxy.js
│   │   ├── middleware/
│   │   │   ├── jwt.js
│   │   │   ├── rateLimit.js
│   │   │   └── logger.js
│   │   └── utils/
│   │       └── healthCheck.js
│   ├── Dockerfile
│   ├── package.json
│   └── .env.example
│
├── oauth-server/                   # [A1] JWT & OAuth 2.0 Server
│   ├── src/
│   │   ├── index.js
│   │   ├── routes/
│   │   │   └── oauth.js
│   │   └── models/
│   │       └── token.js
│   ├── Dockerfile
│   ├── package.json
│   └── .env.example
│
├── php-citizen/                    # [A3] Citizen Service PHP MVC
│   ├── app/
│   │   ├── Controllers/
│   │   │   ├── CitizenController.php
│   │   │   ├── ReportController.php
│   │   │   └── NotifController.php
│   │   ├── Models/
│   │   │   ├── Citizen.php
│   │   │   ├── Report.php
│   │   │   └── Notification.php
│   │   ├── Services/
│   │   │   └── RabbitMQPublisher.php
│   │   └── Validators/
│   │       └── CitizenValidator.php
│   ├── config/
│   │   └── database.php
│   ├── public/
│   │   └── index.php
│   ├── Dockerfile
│   └── .env.example
│
├── php-traffic/                    # [A2] Traffic Service PHP MVC
│   ├── app/
│   │   ├── Controllers/
│   │   │   ├── TrafficController.php
│   │   │   ├── RoadController.php
│   │   │   └── IncidentController.php
│   │   ├── Models/
│   │   │   ├── TrafficData.php
│   │   │   ├── Road.php
│   │   │   └── Incident.php
│   │   ├── Services/
│   │   │   └── RabbitMQPublisher.php
│   │   └── Validators/
│   │       └── TrafficValidator.php
│   ├── config/
│   │   └── database.php
│   ├── public/
│   │   └── index.php
│   ├── Dockerfile
│   └── .env.example
│
├── php-parking/                    # [A3] Parking Service PHP MVC
│   ├── app/
│   │   ├── Controllers/
│   │   │   ├── ParkingController.php
│   │   │   └── ReservationController.php
│   │   ├── Models/
│   │   │   ├── ParkingSlot.php
│   │   │   └── Reservation.php
│   │   ├── Services/
│   │   │   └── RabbitMQPublisher.php
│   │   └── Validators/
│   │       └── ParkingValidator.php
│   ├── config/
│   │   └── database.php
│   ├── public/
│   │   └── index.php
│   ├── Dockerfile
│   └── .env.example
│
├── python-ml-service/              # [A4] Python ML FastAPI
│   ├── main.py
│   ├── train_models.py
│   ├── consumers/
│   │   ├── traffic_consumer.py
│   │   └── anomaly_publisher.py
│   ├── models/                     # .pkl files — ADA di .gitignore
│   ├── data/
│   │   ├── traffic_history.csv
│   │   ├── parking_history.csv
│   │   └── sensor_readings.csv
│   ├── notebooks/
│   │   └── EDA.ipynb
│   ├── requirements.txt
│   ├── Dockerfile
│   └── .env.example
│
├── iot/                            # [A2] IoT Layer
│   ├── simulator.py
│   ├── mosquitto.conf
│   ├── passwd                      # [JANGAN DI-COMMIT] — ada di .gitignore
│   └── node-red-data/
│       └── flows.json
│
├── database/                       # [A5 + semua] Skema DB
│   ├── schema.sql
│   └── seed.sql
│
├── k8s/                            # [A5] Kubernetes Manifests
│   ├── namespace.yaml
│   ├── configmap.yaml
│   ├── secrets.yaml
│   ├── mysql-statefulset.yaml
│   ├── rabbitmq-deployment.yaml
│   ├── gateway-deployment.yaml
│   ├── python-ml-deployment.yaml
│   ├── php-deployments.yaml
│   ├── ingress.yaml
│   └── hpa.yaml
│
├── monitoring/                     # [A5] Monitoring
│   ├── prometheus.yml
│   └── grafana-dashboard.json
│
├── docs/                           # [Semua] Dokumentasi
│   ├── architecture-diagram.png
│   ├── sequence-diagram-S1.png
│   └── sequence-diagram-S2.png
│
├── postman/                        # [Semua] Postman Collection
│   └── SmartTraffic.postman_collection.json
│
├── docker-compose.yml              # [A5] Docker Compose utama
├── docker-compose.dev.yml          # [A5] Override untuk development
├── .env.example                    # [A1 + A5] Template env var
├── .gitignore
├── Makefile                        # [A5] Shortcut perintah
└── README.md                       # [Semua] Dokumentasi utama
```

---

## 4. Standar Kode & Konvensi Bersama

### Format Response JSON (WAJIB semua service)

Semua endpoint PHP dan Python **harus** mengembalikan response dalam format berikut:

```json
{
  "status": "success",
  "code": 200,
  "data": { ... },
  "message": "Keterangan singkat",
  "timestamp": "2025-01-01T00:00:00.000Z",
  "service": "traffic-service"
}
```

HTTP status code yang digunakan: `200`, `201`, `400`, `401`, `403`, `404`, `422`, `429`, `500`, `502`, `503`.

### Konvensi Penamaan

| Item | Konvensi | Contoh |
|---|---|---|
| Tabel DB | `snake_case` (prefix service) | `traffic_readings`, `citizen_reports` |
| Kolom DB | `snake_case` | `vehicle_density`, `recorded_at` |
| MQTT Topic | `city/{zone}/{type}` | `city/zone1/traffic` |
| RabbitMQ Queue | `{service}.{event}` | `traffic.new`, `anomaly.alert` |
| Docker service | `kebab-case` | `api-gateway`, `python-ml` |
| Kubernetes namespace | `smartcity` | (use `smartcity`) |

### Variabel Environment (`.env.example` root)

```env
# Database
DB_HOST=mysql
DB_PORT=3306
DB_NAME=smartcity
DB_ROOT_PASSWORD=GANTI_INI
DB_USER=smartcity_app
DB_PASSWORD=GANTI_INI

# Auth
JWT_SECRET=GANTI_DENGAN_STRING_RANDOM_PANJANG
JWT_EXPIRES_IN=3600
OAUTH_CLIENT_ID=smartcity_client
OAUTH_CLIENT_SECRET=GANTI_INI

# Service URLs (untuk Gateway)
CITIZEN_SERVICE_URL=http://citizen-service:8000
TRAFFIC_SERVICE_URL=http://traffic-service:8001
PARKING_SERVICE_URL=http://parking-service:8002
PYTHON_ML_URL=http://python-ml:5000

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=smartcity
RABBITMQ_PASS=GANTI_INI

# MQTT
MQTT_BROKER=mosquitto
MQTT_PORT=1883
MQTT_USER=iot_device
MQTT_PASS=GANTI_INI
```

> ⚠️ **WAJIB:** File `.env` **tidak boleh** di-push ke Git. Pastikan `.env` ada di `.gitignore`.

### `.gitignore` yang Harus Ada

```gitignore
# Environment
.env
*.env.local

# Python
__pycache__/
*.pyc
venv/
python-ml-service/models/*.pkl

# Node.js
node_modules/

# MQTT credentials
iot/passwd

# OS
.DS_Store
Thumbs.db

# Logs
*.log
```

---

## 5. Panduan Setup Lokal

### Prasyarat

| Tool | Versi Minimum | Siapa yang butuh |
|---|---:|---|
| Docker Desktop | 24.x | Semua |
| Docker Compose | v2 | Semua |
| Node.js | 18.x LTS | A1 |
| PHP | 8.2 | A2, A3 |
| Python | 3.11 | A4 |
| kubectl | 1.28+ | A5 |
| Git | 2.x | Semua |

### Langkah Setup Pertama Kali

```bash
# 1. Clone repository
git clone https://github.com//smart-traffic-platform.git
cd smart-traffic-platform

# 2. Salin template env dan isi nilainya
cp .env.example .env
# Edit .env dengan editor favorit (VS Code, nano, dll)

# 3. Build dan jalankan semua service
docker compose up -d --build

# 4. Cek semua container healthy
docker compose ps

# 5. Jalankan migrasi dan seed database
docker exec -i $(docker compose ps -q mysql) mysql -u root -p${DB_ROOT_PASSWORD} smartcity < database/schema.sql
docker exec -i $(docker compose ps -q mysql) mysql -u root -p${DB_ROOT_PASSWORD} smartcity < database/seed.sql

# 6. Verifikasi semua service berjalan
curl http://localhost:3000/health
```

### Perintah Sehari-hari

```bash
# Jalankan ulang satu service setelah ada perubahan kode
docker compose up -d --build traffic-service

# Lihat log real-time service tertentu
docker compose logs -f python-ml

# Restart service tanpa rebuild
docker compose restart citizen-service

# Stop semua
docker compose down

# Stop dan hapus volume (reset data)
docker compose down -v
```

---

## 6. Panduan Per Anggota

---

### Anggota 1 — API Gateway + OAuth 2.0 + JWT + Rate Limiting

**Materi terkait:** Per. 5 (JWT), Per. 6 (OAuth 2.0), Per. 7 (API Gateway), Per. 10 (Rate Limiting)

#### Yang Harus Dibuat

**A. OAuth 2.0 Server** (`oauth-server/`, port 3002)

Implementasikan 3 grant type:
- `password` — untuk login warga (username + password → access_token + refresh_token)
- `client_credentials` — untuk sensor IoT dan komunikasi antar service
- `refresh_token` — perpanjang sesi tanpa login ulang

Endpoint wajib:
```
POST /oauth/token       → issue token
POST /oauth/introspect  → validasi token (dipanggil oleh Gateway)
POST /oauth/revoke      → cabut token
```

**B. API Gateway** (`express-gateway/`, port 3000)

Wajib mengimplementasikan:
- JWT Middleware: verifikasi token di setiap protected endpoint
- OAuth Introspection: panggil `/oauth/introspect` untuk validasi
- Routing: forward ke service berdasarkan path prefix:
  - `/api/citizens/*` → `http://citizen-service:8000`
  - `/api/traffic/*` → `http://traffic-service:8001`
  - `/api/parking/*` → `http://parking-service:8002`
  - `/predict/*` atau `/detect/*` → `http://python-ml:5000`
  - `/iot/*` → `http://traffic-service:8001/iot` (dari Node-RED)
- Rate Limiting:
  - Global (per IP): 100 request / 15 menit
  - Authenticated (per token): 500 request / jam
- Request logging: catat semua request dengan timestamp, method, path, status, response time
- Health Aggregator: `GET /health` harus cek semua upstream service

Contoh kode rate limiter (sudah disediakan di PDF modul, gunakan ini):
```javascript
// src/middleware/rateLimit.js
const rateLimit = require('express-rate-limit');

const globalLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 100,
  standardHeaders: true,
  legacyHeaders: false,
  message: { status: "error", code: 429, message: "Too many requests" },
});

const authLimiter = rateLimit({
  windowMs: 60 * 60 * 1000,
  max: 500,
  keyGenerator: (req) => req.headers.authorization || req.ip,
});

module.exports = { globalLimiter, authLimiter };
```

#### Dependency ke Anggota Lain

- Butuh schema tabel `oauth_clients` dan `oauth_tokens` dari **A5** (database)
- Koordinasi URL routing dengan **A2** dan **A3** (service URLs)
- Beri tahu **A4** format token yang digunakan (agar ML service bisa verifikasi)

#### Checklist Anggota 1

- [ ] `POST /oauth/token` berjalan dengan grant type `password`
- [ ] `POST /oauth/token` berjalan dengan grant type `client_credentials`
- [ ] `POST /oauth/introspect` mengembalikan status token valid/invalid
- [ ] JWT middleware memblokir request tanpa token (return 401)
- [ ] Rate limit aktif — test dengan 101 request berturut-turut (harus dapat 429)
- [ ] `GET /health` mengembalikan status semua service upstream
- [ ] Semua credential disimpan di `.env`, tidak hardcode

---

### Anggota 2 — Traffic Service + IoT Layer

**Materi terkait:** Per. 3-4 (PHP MVC REST API), Per. 9 (RabbitMQ), IoT (MQTT + Node-RED)

#### Yang Harus Dibuat

**A. Traffic Service** (`php-traffic/`, port 8001)

Controller dan endpoint wajib:

| Controller | Endpoint | Method | Deskripsi |
|---|---|---|---|
| TrafficController | `/api/traffic/readings` | POST | Simpan data sensor lalu lintas (publish ke RabbitMQ) |
| TrafficController | `/api/traffic/current` | GET | Status real-time per zona |
| TrafficController | `/api/traffic/history` | GET | Riwayat (filter tanggal, zona) |
| RoadController | `/api/roads` | GET | Daftar segmen jalan |
| IncidentController | `/api/incidents` | POST | Laporkan insiden baru |
| IncidentController | `/api/incidents` | GET | Daftar insiden aktif |
| IncidentController | `/api/incidents/:id/resolve` | PATCH | Tandai insiden selesai |

Setiap kali `POST /api/traffic/readings` berhasil, **wajib publish** event ke RabbitMQ:
```php
$this->publisher->publish('traffic.new', [
    'id'         => $record['id'],
    'location'   => $record['zone_id'],
    'speed_kmh'  => $record['avg_speed_kmh'],
    'density'    => $record['vehicle_density'],
    'timestamp'  => $record['recorded_at'],
]);
```

**B. Sensor Simulator** (`iot/simulator.py`)

Script ini berjalan sebagai background process dan mensimulasikan 4 zona (`zone1`–`zone4`). Data publish setiap **30 detik** via MQTT:

```python
# Pattern rush hour pagi (07.00-09.00) dan sore (16.00-19.00)
# dengan noise Gaussian ±8 kendaraan/menit
import math, random

def simulate_traffic(zone, hour):
    base = 30 + 50 * math.sin((hour - 7) * math.pi / 12) if 7 <= hour <= 19 else 10
    return max(0, base + random.gauss(0, 8))
```

MQTT topic: `city/{zone}/traffic` dan `city/{zone}/parking`

**C. Node-RED Flow** (`iot/node-red-data/flows.json`)

Buat minimal 2 flow di Node-RED (import via UI di port 1880):
1. **Traffic Flow:** MQTT in (`city/+/traffic`) → Function (parse + validasi) → HTTP POST ke `http://api-gateway:3000/iot/traffic`
2. **Parking Flow:** MQTT in (`city/+/parking`) → Function → HTTP POST ke `http://api-gateway:3000/iot/parking`

Tambahkan error handling: jika Gateway down, simpan ke buffer Node-RED dan retry setiap 60 detik.

**D. Konfigurasi Mosquitto** (`iot/mosquitto.conf`)

```
listener 1883
allow_anonymous false
password_file /mosquitto/config/passwd
```

Buat file passwd dengan perintah:
```bash
docker run --rm eclipse-mosquitto:2.0 mosquitto_passwd -c /tmp/passwd iot_device
# salin output ke iot/passwd
```

#### Dependency ke Anggota Lain

- Koordinasi dengan **A1** untuk endpoint `/iot/traffic` dan `/iot/parking` di Gateway
- Koordinasi dengan **A4** tentang format payload RabbitMQ (queue `traffic.new`)
- Koordinasi dengan **A5** untuk Dockerfile dan service name di docker-compose

#### Checklist Anggota 2

- [ ] `POST /api/traffic/readings` menyimpan ke DB dan publish ke RabbitMQ
- [ ] `GET /api/traffic/current` mengembalikan data terbaru per zona
- [ ] Simulator berjalan dan publish data ke MQTT setiap 30 detik
- [ ] Node-RED menerima data MQTT dan forward ke Gateway
- [ ] Data masuk ke DB dalam 60 detik setelah simulator jalan (syarat S1)
- [ ] `GET /health` mengembalikan status DB connection

---

### Anggota 3 — Parking Service + Citizen Service + RabbitMQ

**Materi terkait:** Per. 3-4 (PHP MVC), Per. 9 (RabbitMQ AMQP)

#### Yang Harus Dibuat

**A. Parking Service** (`php-parking/`, port 8002)

| Controller | Endpoint | Method | Deskripsi |
|---|---|---|---|
| ParkingController | `/api/parking/zones` | GET | Daftar area parkir |
| ParkingController | `/api/parking/slots` | GET | Slot tersedia (filter by zone) |
| ParkingController | `/api/parking/slots/:id` | GET | Detail slot |
| ReservationController | `/api/parking/reserve` | POST | Reservasi slot (butuh JWT) |
| ReservationController | `/api/parking/checkin/:id` | PATCH | Check-in (butuh JWT) |
| ReservationController | `/api/parking/checkout/:id` | PATCH | Check-out dan hitung durasi |
| ReservationController | `/api/parking/history` | GET | Riwayat parkir user login |

**B. Citizen Service** (`php-citizen/`, port 8000)

| Controller | Endpoint | Method | Deskripsi |
|---|---|---|---|
| CitizenController | `/api/citizens` | POST | Daftarkan warga baru |
| CitizenController | `/api/citizens/:id` | GET | Profil warga |
| CitizenController | `/api/citizens/:id` | PUT | Update profil |
| ReportController | `/api/reports` | POST | Submit laporan (kemacetan/insiden) |
| ReportController | `/api/reports` | GET | List laporan (filter status, zona) |
| ReportController | `/api/reports/:id/status` | PATCH | Update status (Admin only) |
| NotifController | `/api/notifications` | GET | Notifikasi warga yang login |
| NotifController | `/api/notifications/:id/read` | PATCH | Tandai notifikasi sudah dibaca |

Saat warga submit laporan (`POST /api/reports`), **publish ke RabbitMQ**:
```php
$this->publisher->publish('report.submitted', [
    'report_id'  => $report['id'],
    'citizen_id' => $report['citizen_id'],
    'zone_id'    => $report['zone_id'],
    'category'   => $report['category'],
]);
```

**C. RabbitMQ Setup**

Buat file `iot/rabbitmq-init.sh` untuk setup exchange dan queue saat pertama kali:

```bash
#!/bin/bash
# Tunggu RabbitMQ siap
until rabbitmqctl status; do sleep 2; done

# Buat exchange dan queue via management API
curl -u guest:guest -X PUT http://localhost:15672/api/exchanges/%2F/city.events \
  -H "Content-Type: application/json" \
  -d '{"type":"topic","durable":true}'

# Queue yang dibutuhkan
for queue in traffic.new parking.update anomaly.alert report.submitted; do
  curl -u guest:guest -X PUT "http://localhost:15672/api/queues/%2F/$queue" \
    -H "Content-Type: application/json" \
    -d '{"durable":true}'
done
```

**Tabel Exchange & Queue yang harus berjalan:**

| Exchange | Queue | Publisher | Consumer |
|---|---|---|---|
| city.events | traffic.new | Traffic PHP | Python ML |
| city.events | parking.update | Parking PHP | Python ML |
| city.events | anomaly.alert | Python ML | Citizen PHP (notification) |
| city.events | report.submitted | Citizen PHP | Notification Worker |

**D. Notification Consumer**

Buat `php-citizen/app/Services/NotificationConsumer.php` — script PHP yang berjalan terus-menerus, mendengarkan queue `anomaly.alert`, dan membuat notifikasi ke warga di zona terdampak.

#### Dependency ke Anggota Lain

- Koordinasi dengan **A1** untuk format JWT payload (harus ada `citizen_id`, `role`)
- Koordinasi dengan **A4** tentang format pesan anomaly alert dari RabbitMQ
- Koordinasi dengan **A5** untuk Dockerfile dan healthcheck

#### Checklist Anggota 3

- [ ] `POST /api/reports` berhasil simpan ke DB dan publish ke RabbitMQ
- [ ] `GET /api/notifications` mengembalikan notif untuk user yang login
- [ ] Slot parkir bisa direservasi dan checkout
- [ ] RabbitMQ Management UI bisa diakses di port 15672
- [ ] Minimal 2 queue aktif (traffic.new dan anomaly.alert) — bisa dicek di Management UI
- [ ] Notification consumer berjalan dan membuat notif saat menerima alert

---

### Anggota 4 — Python ML Service

**Materi terkait:** Per. 11 (Python ML + FastAPI), Per. 9 (RabbitMQ Consumer)

#### Yang Harus Dibuat

**A. 3 Model Machine Learning**

Semua model dilatih di `train_models.py` dan disimpan ke `models/smartcity_models.pkl`:

**Model 1 — Traffic Density Predictor (Regression)**
- Algoritma: `RandomForestRegressor`
- Input fitur: `hour`, `day_of_week`, `weather_code`, `prev_density`, `location_enc`
- Output: `predicted_density` (kendaraan/menit), `congestion_level` (Lancar/Sedang/Padat)
- Target evaluasi: **R² ≥ 0.70**

**Model 2 — Parking Occupancy Forecast (Regression/Classification)**
- Algoritma: `GradientBoostingRegressor` atau `LinearRegression`
- Input fitur: `hour`, `day_of_week`, `zone_id`, `historical_avg_occupancy`
- Output: `occupancy_rate` (0.0–1.0), `availability_label` (Tersedia/Penuh/Hampir Penuh)
- Target evaluasi: **R² ≥ 0.70**

**Model 3 — Anomaly Detector (Unsupervised)**
- Algoritma: `IsolationForest`
- Input fitur: `sensor_value`, `timestamp_hour`, `rolling_mean_1h`, `z_score`
- Output: `is_anomaly` (bool), `anomaly_score`, `severity` (Kritis/Peringatan/Normal)
- Parameter: `contamination=0.05` (5% data dianggap anomali)

**B. Dataset**

Buat dataset sintetis minimal **5.000 baris** menggunakan `generate_dataset.py`:

```python
# generate_dataset.py
import pandas as pd, numpy as np, random, math
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
df.to_csv('data/traffic_history.csv', index=False)
print(f"Generated {len(df)} rows")
```

**C. FastAPI Endpoints** (`main.py`, port 5000)

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| GET | `/health` | Tidak | Status service + model yang terload |
| POST | `/predict/traffic` | JWT | Prediksi kepadatan lalu lintas |
| POST | `/predict/parking` | JWT | Prediksi occupancy parkir |
| POST | `/detect/anomaly` | JWT | Deteksi anomali sensor |
| GET | `/model/feature-importance` | JWT | Bobot fitur ketiga model |
| POST | `/predict/batch` | JWT | Batch prediction (array input) |

**D. RabbitMQ Consumer** (`consumers/traffic_consumer.py`)

Consumer mendengarkan queue `traffic.new`, memproses prediksi, dan jika anomali terdeteksi, **publish ke queue `anomaly.alert`**:

```python
def callback(ch, method, props, body):
    event = json.loads(body)
    # ... prediksi ...
    
    # Jika anomali terdeteksi
    if is_anomaly:
        alert_payload = {
            'zone_id': event['location'],
            'sensor_value': event['density'],
            'anomaly_score': score,
            'severity': severity,
            'timestamp': event['timestamp'],
        }
        ch.basic_publish(
            exchange='city.events',
            routing_key='anomaly.alert',
            body=json.dumps(alert_payload),
        )
    
    ch.basic_ack(delivery_tag=method.delivery_tag)
```

**E. ML Report** (`notebooks/EDA.ipynb`)

Notebook harus berisi:
1. Deskripsi dataset (jumlah baris, kolom, tipe data)
2. EDA: distribusi variabel, korelasi antar fitur, visualisasi pola rush hour
3. Preprocessing: normalisasi (StandardScaler), encoding (LabelEncoder)
4. Training ketiga model
5. Evaluasi: R²/Accuracy, Cross-Validation (cv=5), confusion matrix
6. Kesimpulan dan analisis hasil

#### Dependency ke Anggota Lain

- Butuh RabbitMQ sudah running (**A3**) sebelum consumer bisa jalan
- Koordinasi format payload `traffic.new` dengan **A2**
- Koordinasi format alert `anomaly.alert` dengan **A3** (Notification Consumer)

#### Checklist Anggota 4

- [ ] `train_models.py` berhasil menghasilkan `models/smartcity_models.pkl`
- [ ] `POST /predict/traffic` mengembalikan respons dalam < 500ms
- [ ] `POST /predict/parking` berjalan
- [ ] `POST /detect/anomaly` berjalan
- [ ] R² atau Accuracy ketiga model ≥ 70% (ditunjukkan di notebook)
- [ ] Consumer mendengarkan `traffic.new` dan publish ke `anomaly.alert`
- [ ] Notebook EDA.ipynb lengkap dengan visualisasi

---

### Anggota 5 — DevOps + Infrastruktur

**Materi terkait:** Per. 12 (Docker), Per. 13 (Kubernetes)

#### Yang Harus Dibuat

**A. Dockerfile per Service**

Buat Dockerfile untuk masing-masing service. Contoh standar yang harus diikuti:

```dockerfile
# Contoh: php-traffic/Dockerfile
FROM php:8.2-fpm-alpine
RUN docker-php-ext-install pdo pdo_mysql
RUN apk add --no-cache nginx supervisor
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader
HEALTHCHECK --interval=30s --timeout=10s --retries=3 \
  CMD curl -f http://localhost:8001/health || exit 1
EXPOSE 8001
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
```

**B. Docker Compose** (`docker-compose.yml`)

Semua service dalam satu file, jalankan dengan `docker compose up -d --build`. Pastikan:
- Semua service ada `healthcheck`
- Ada `depends_on` dengan `condition: service_healthy` untuk DB
- Semua dalam network `smartcity-net`
- Volume untuk MySQL dan RabbitMQ persistence

**C. Kubernetes Manifests** (`k8s/`)

File yang harus dibuat:

| File | Kind | Keterangan |
|---|---|---|
| `namespace.yaml` | Namespace | Namespace `smartcity` |
| `configmap.yaml` | ConfigMap | URL service, port, feature flags |
| `secrets.yaml` | Secret | DB password, JWT secret (base64) |
| `mysql-statefulset.yaml` | StatefulSet + PVC | MySQL dengan persistent storage |
| `rabbitmq-deployment.yaml` | Deployment + Service | RabbitMQ |
| `gateway-deployment.yaml` | Deployment + Service | API Gateway, 2 replika |
| `python-ml-deployment.yaml` | Deployment + Service + HPA | ML Service, scale 1–5 pod |
| `php-deployments.yaml` | Deployments + Services | Citizen, Traffic, Parking |
| `ingress.yaml` | Ingress | Route traffic eksternal |
| `hpa.yaml` | HorizontalPodAutoscaler | Scale ML saat CPU > 70% |

HPA untuk Python ML:
```yaml
# k8s/hpa.yaml
spec:
  scaleTargetRef:
    kind: Deployment
    name: python-ml
  minReplicas: 1
  maxReplicas: 5
  metrics:
    - type: Resource
      resource:
        name: cpu
        target:
          type: Utilization
          averageUtilization: 70
```

**D. Monitoring**

`monitoring/prometheus.yml` — scrape semua service:
```yaml
scrape_configs:
  - job_name: 'api-gateway'
    static_configs:
      - targets: ['api-gateway:3000']
  - job_name: 'python-ml'
    static_configs:
      - targets: ['python-ml:5000']
  # tambahkan semua service lain
```

Grafana Dashboard (`monitoring/grafana-dashboard.json`) harus punya minimal **5 panel**:
1. Request rate (req/s) per service
2. Error rate (%) per service
3. Response latency P95 (ms)
4. Container CPU usage
5. Container Memory usage
6. *(Bonus)* RabbitMQ queue depth

**E. Makefile** — shortcut perintah umum:
```makefile
up:
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f $(service)

db-init:
	docker exec -i $$(docker compose ps -q mysql) mysql -u root -p$${DB_ROOT_PASSWORD} smartcity < database/schema.sql
	docker exec -i $$(docker compose ps -q mysql) mysql -u root -p$${DB_ROOT_PASSWORD} smartcity < database/seed.sql

k8s-deploy:
	kubectl apply -f k8s/ -n smartcity

k8s-status:
	kubectl get pods -n smartcity -w
```

**F. Schema Database & Seed** (`database/`)

Koordinasikan dengan A2 dan A3 untuk skema lengkap. Pastikan `schema.sql` bisa dijalankan langsung tanpa error:

```sql
CREATE DATABASE IF NOT EXISTS smartcity;
USE smartcity;

-- Shared Tables
CREATE TABLE zones (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL,
  city_district VARCHAR(100),
  coordinates VARCHAR(100),
  area_km2 DECIMAL(5,2),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE oauth_clients (
  id INT PRIMARY KEY AUTO_INCREMENT,
  client_id VARCHAR(100) UNIQUE NOT NULL,
  client_secret VARCHAR(255) NOT NULL,
  grant_types VARCHAR(200),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE oauth_tokens (
  id INT PRIMARY KEY AUTO_INCREMENT,
  client_id VARCHAR(100),
  user_id INT,
  access_token VARCHAR(512) UNIQUE NOT NULL,
  refresh_token VARCHAR(512),
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_access_token (access_token),
  INDEX idx_expires_at (expires_at)
);
-- ... (tambahkan semua tabel dari section 9)
```

#### Dependency ke Anggota Lain

- Butuh `.env.example` yang lengkap dari **A1**
- Butuh konfirmasi port dari semua anggota sebelum membuat docker-compose
- Koordinasi nama image Docker dengan semua anggota

#### Checklist Anggota 5

- [ ] `docker compose up -d --build` berhasil tanpa error
- [ ] Semua 10+ container berstatus `healthy` (cek dengan `docker compose ps`)
- [ ] `database/schema.sql` bisa dijalankan langsung
- [ ] `database/seed.sql` menghasilkan minimal 200 baris data
- [ ] `kubectl apply -f k8s/ -n smartcity` berhasil
- [ ] Semua pod berstatus `Running` di namespace `smartcity`
- [ ] HPA terkonfigurasi untuk python-ml
- [ ] Grafana dashboard aktif dengan minimal 5 panel

---

## 7. Alur Kerja Git

### Setup Branch

```bash
# Dari main, buat branch untuk fitur masing-masing
git checkout -b feat/gateway-oauth          # A1
git checkout -b feat/traffic-service        # A2
git checkout -b feat/citizen-parking        # A3
git checkout -b feat/ml-service             # A4
git checkout -b feat/docker-k8s             # A5
```

### Workflow Harian

```bash
# Sebelum mulai kerja, selalu update dari main
git checkout main
git pull origin main
git checkout feat/nama-branch-kamu
git rebase main

# Setelah selesai fitur
git add .
git commit -m "feat(traffic): tambah endpoint POST /api/traffic/readings"
git push origin feat/nama-branch-kamu

# Buat Pull Request ke main melalui GitHub
# Minta minimal 1 anggota lain untuk review sebelum merge
```

### Konvensi Commit Message

```
feat(service): tambah fitur baru
fix(service): perbaiki bug
docs: update README
chore: update dependency
test: tambah unit test
```

### Branch Protection Rules (setup di GitHub)

- Branch `main` harus diproteksi
- Tidak boleh push langsung ke `main`
- Setiap merge ke `main` harus melalui Pull Request
- Minimal 1 reviewer untuk PR

---

## 8. Skenario Demo End-to-End

Minimal **5 dari 6** skenario ini harus berjalan live saat presentasi. Latih urutan ini sebelum hari demo.

### S1 — IoT Data Ingestion (A2 lead, A4 support)

```
Simulator Python
  → publish MQTT ke city/zone1/traffic
  → Node-RED subscribe & parse
  → HTTP POST ke Gateway :3000/iot/traffic
  → Gateway forward ke Traffic Service :8001
  → PHP simpan ke DB (traffic_readings)
  → PHP publish ke RabbitMQ (traffic.new)
  → Python ML consume & prediksi
```
**Cara demo:** Jalankan simulator, buka Node-RED dashboard, query DB untuk konfirmasi data masuk.

### S2 — Citizen Login & Report (A1 + A3 lead)

```
POST /oauth/token (grant: password)
  → dapat access_token
  → POST /api/reports dengan Bearer token
  → Gateway verifikasi JWT
  → Citizen Service simpan ke DB
  → Publish event ke RabbitMQ (report.submitted)
  → Notification worker consume → buat notifikasi
  → GET /api/notifications untuk cek notifikasi masuk
```
**Cara demo:** Gunakan Postman, lakukan login, submit laporan, cek notifikasi.

### S3 — ML Real-time Prediction (A4 lead, A1 support)

```
POST /predict/traffic (dengan Bearer token)
  → Gateway cek rate limit
  → Forward ke Python ML :5000
  → ML proses prediksi
  → Return predicted_density + congestion_level
```
**Cara demo:** Postman `POST /predict/traffic` dengan payload sensor. Kemudian kirim 101 request berturut-turut untuk trigger rate limit 429.

### S4 — Docker Full Stack (A5 lead)

```
docker compose up -d --build
  → Semua 10+ container running
  → curl /health → semua service healthy
  → Simulator jalan → data masuk DB
```
**Cara demo:** Jalankan `docker compose ps` di terminal, tampilkan semua status `healthy`.

### S5 — Kubernetes Deployment (A5 lead)

```
kubectl apply -f k8s/ -n smartcity
  → Semua pod Running
  → curl via Ingress ke Gateway
  → Trigger HPA: kirim banyak request → python-ml scale 2+ pod
  → kubectl set image → rolling update zero downtime
```
**Cara demo:** Tampilkan `kubectl get pods -n smartcity -w` di terminal saat update berlangsung.

### S6 — Anomaly Alert Flow (A4 + A3 lead)

```
Simulator kirim nilai ekstrem (density > 150)
  → Python ML detect anomali
  → Publish ke RabbitMQ (anomaly.alert)
  → Citizen Service consume
  → Buat notifikasi urgent untuk warga di zona terdampak
  → GET /api/notifications → tampilkan notifikasi kritis
```
**Cara demo:** Modifikasi simulator untuk kirim nilai 200 kendaraan/menit sementara, pantau notifikasi yang muncul.

---

## 9. Skema Database Lengkap

Semua tabel di bawah ini masuk ke satu database `smartcity` dengan prefix per service.

```sql
-- =============================================
-- SHARED TABLES
-- =============================================
CREATE TABLE zones (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL,
  city_district VARCHAR(100),
  coordinates VARCHAR(100),
  area_km2 DECIMAL(5,2),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================
-- CITIZEN SERVICE TABLES
-- =============================================
CREATE TABLE citizens (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nik VARCHAR(16) UNIQUE NOT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(20),
  zone_id INT,
  role ENUM('citizen','admin') DEFAULT 'citizen',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (zone_id) REFERENCES zones(id),
  INDEX idx_email (email),
  INDEX idx_zone_id (zone_id)
);

CREATE TABLE reports (
  id INT PRIMARY KEY AUTO_INCREMENT,
  citizen_id INT NOT NULL,
  category ENUM('kemacetan','kecelakaan','jalan_rusak','parkir_liar','lainnya'),
  description TEXT,
  zone_id INT,
  latitude DECIMAL(10,7),
  longitude DECIMAL(10,7),
  status ENUM('reported','verified','in_progress','resolved') DEFAULT 'reported',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (citizen_id) REFERENCES citizens(id),
  FOREIGN KEY (zone_id) REFERENCES zones(id),
  INDEX idx_status (status),
  INDEX idx_zone_id (zone_id)
);

CREATE TABLE notifications (
  id INT PRIMARY KEY AUTO_INCREMENT,
  citizen_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  body TEXT,
  type ENUM('info','warning','critical') DEFAULT 'info',
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (citizen_id) REFERENCES citizens(id),
  INDEX idx_citizen_id (citizen_id),
  INDEX idx_is_read (is_read)
);

-- =============================================
-- TRAFFIC SERVICE TABLES
-- =============================================
CREATE TABLE traffic_readings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  zone_id INT NOT NULL,
  vehicle_density DECIMAL(8,2) COMMENT 'kendaraan per menit',
  avg_speed_kmh DECIMAL(5,2),
  incident_flag TINYINT(1) DEFAULT 0,
  source ENUM('sensor','manual','iot') DEFAULT 'iot',
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (zone_id) REFERENCES zones(id),
  INDEX idx_zone_id (zone_id),
  INDEX idx_recorded_at (recorded_at)
);

CREATE TABLE incidents (
  id INT PRIMARY KEY AUTO_INCREMENT,
  zone_id INT NOT NULL,
  type ENUM('kecelakaan','kemacetan_parah','jalan_rusak','banjir','lainnya'),
  severity ENUM('rendah','sedang','tinggi','kritis') DEFAULT 'sedang',
  description TEXT,
  reported_by INT COMMENT 'citizen_id jika dari laporan warga',
  resolved_at TIMESTAMP NULL,
  reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (zone_id) REFERENCES zones(id),
  INDEX idx_zone_id (zone_id),
  INDEX idx_severity (severity)
);

-- =============================================
-- PARKING SERVICE TABLES
-- =============================================
CREATE TABLE parking_zones (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  zone_id INT NOT NULL,
  total_slots INT DEFAULT 0,
  type ENUM('umum','khusus','vip') DEFAULT 'umum',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (zone_id) REFERENCES zones(id)
);

CREATE TABLE parking_slots (
  id INT PRIMARY KEY AUTO_INCREMENT,
  parking_zone_id INT NOT NULL,
  slot_number VARCHAR(10) NOT NULL,
  status ENUM('available','occupied','reserved','maintenance') DEFAULT 'available',
  last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (parking_zone_id) REFERENCES parking_zones(id),
  INDEX idx_status (status),
  INDEX idx_parking_zone_id (parking_zone_id)
);

CREATE TABLE parking_reservations (
  id INT PRIMARY KEY AUTO_INCREMENT,
  citizen_id INT NOT NULL,
  slot_id INT NOT NULL,
  reserved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  checked_in_at TIMESTAMP NULL,
  checked_out_at TIMESTAMP NULL,
  duration_minutes INT NULL,
  status ENUM('reserved','active','completed','cancelled') DEFAULT 'reserved',
  FOREIGN KEY (citizen_id) REFERENCES citizens(id),
  FOREIGN KEY (slot_id) REFERENCES parking_slots(id),
  INDEX idx_citizen_id (citizen_id),
  INDEX idx_status (status)
);

-- =============================================
-- OAUTH TABLES
-- =============================================
CREATE TABLE oauth_clients (
  id INT PRIMARY KEY AUTO_INCREMENT,
  client_id VARCHAR(100) UNIQUE NOT NULL,
  client_secret VARCHAR(255) NOT NULL,
  grant_types VARCHAR(200) DEFAULT 'password,client_credentials,refresh_token',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE oauth_tokens (
  id INT PRIMARY KEY AUTO_INCREMENT,
  client_id VARCHAR(100) NOT NULL,
  user_id INT NULL,
  access_token VARCHAR(512) UNIQUE NOT NULL,
  refresh_token VARCHAR(512) NULL,
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_access_token (access_token),
  INDEX idx_expires_at (expires_at)
);
```

---

## 10. Spesifikasi API Endpoints

### Auth (OAuth Server :3002)

| Method | Endpoint | Auth | Body | Response |
|---|---|---|---|---|
| POST | `/oauth/token` | Tidak | `grant_type`, `username`, `password` | `access_token`, `refresh_token`, `expires_in` |
| POST | `/oauth/introspect` | Client Secret | `token` | `active`, `user_id`, `scope` |
| POST | `/oauth/revoke` | JWT | `token` | `200 OK` |

### Gateway (:3000)

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| GET | `/health` | Tidak | Status semua upstream service |
| GET | `/metrics` | Internal | Prometheus scrape endpoint |
| POST | `/iot/traffic` | Client Credentials | Dari Node-RED → forward ke Traffic Service |
| POST | `/iot/parking` | Client Credentials | Dari Node-RED → forward ke Parking Service |

### Citizen Service (:8000, via Gateway)

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| POST | `/api/citizens` | Tidak | Register warga baru |
| GET | `/api/citizens/:id` | JWT | Profil warga |
| PUT | `/api/citizens/:id` | JWT | Update profil |
| POST | `/api/reports` | JWT | Submit laporan |
| GET | `/api/reports` | JWT | List laporan (filter: status, zone) |
| PATCH | `/api/reports/:id/status` | JWT+Admin | Update status laporan |
| GET | `/api/notifications` | JWT | Notifikasi warga login |
| PATCH | `/api/notifications/:id/read` | JWT | Tandai sudah dibaca |
| GET | `/health` | Tidak | Status DB connection |

### Traffic Service (:8001, via Gateway)

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| POST | `/api/traffic/readings` | JWT (IoT) | Submit pembacaan sensor (publish ke RabbitMQ) |
| GET | `/api/traffic/current` | JWT | Status real-time per zona |
| GET | `/api/traffic/history` | JWT | Riwayat (filter: tanggal, zona) |
| GET | `/api/roads` | JWT | Daftar segmen jalan |
| POST | `/api/incidents` | JWT | Laporkan insiden |
| GET | `/api/incidents` | JWT | Daftar insiden aktif |
| PATCH | `/api/incidents/:id/resolve` | JWT+Admin | Selesaikan insiden |
| GET | `/health` | Tidak | Status DB connection |

### Parking Service (:8002, via Gateway)

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| GET | `/api/parking/zones` | JWT | Daftar area parkir |
| GET | `/api/parking/slots` | JWT | Slot tersedia (filter: zone) |
| POST | `/api/parking/reserve` | JWT | Reservasi slot |
| PATCH | `/api/parking/checkin/:id` | JWT | Check-in |
| PATCH | `/api/parking/checkout/:id` | JWT | Check-out |
| GET | `/api/parking/history` | JWT | Riwayat parkir user login |
| GET | `/health` | Tidak | Status DB connection |

### Python ML Service (:5000, via Gateway)

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| GET | `/health` | Tidak | Status service + model yang terload |
| POST | `/predict/traffic` | JWT | Prediksi kepadatan lalu lintas |
| POST | `/predict/parking` | JWT | Prediksi occupancy parkir |
| POST | `/detect/anomaly` | JWT | Deteksi anomali sensor |
| GET | `/model/feature-importance` | JWT | Bobot fitur ketiga model |
| POST | `/predict/batch` | JWT | Batch prediction |

---

## 11. Checklist Deliverables

Pastikan semua item ini ada sebelum deadline:

### Wajib Dikumpulkan

- [ ] **Source Code** — Git Repository GitHub/GitLab, branch `main` production-ready
- [ ] **README.md** — Prerequisites, setup `.env`, cara jalankan lokal & di server, cara test
- [ ] **Diagram Arsitektur** — System diagram + sequence diagram minimal S1 dan S2 (PNG/PDF di folder `docs/`)
- [ ] **database/schema.sql** — DDL lengkap dengan `CREATE DATABASE` dan `USE`, bisa dijalankan langsung
- [ ] **database/seed.sql** — Data dummy realistis, minimal 200 baris (5 zona, 50 warga, 200 traffic readings, dll)
- [ ] **Postman Collection** — Semua endpoint, env variable `{{baseUrl}}`, export ke `postman/`
- [ ] **ML Report** — `python-ml-service/notebooks/EDA.ipynb` lengkap
- [ ] **docker-compose.yml** — Satu perintah `docker compose up` jalankan seluruh sistem
- [ ] **k8s/ folder** — Semua manifest K8s, bisa di-apply ke kluster bersih
- [ ] **Demo Video** — MP4, max 15 menit, semua 6 skenario end-to-end di server
- [ ] **Daftar Kontribusi** — Template terlampir, ditandatangani ketua kelompok

### Aturan Pengumpulan

- Submit **link repo + link video** ke Google Form yang dibagikan dosen
- Keterlambatan: **-10 poin per hari** (dihitung per jam)
- **Semua service harus running di server saat demo** — bukan hanya lokal
- Minimal 5 dari 6 skenario bisa didemonstrasikan live

---

## 12. Jadwal Kerja yang Disarankan

> Sesuaikan dengan deadline aktual dari dosen. Jadwal ini mengasumsikan sisa waktu ±4 minggu.

### Minggu 1 — Fondasi (Prioritas: A1, A5)

- Hari 1–2: Setup GitHub Organization, repo, branch protection, `.gitignore`, dan `.env.example`
- Hari 3–4: A5 — Buat `docker-compose.yml` dasar (MySQL + RabbitMQ + Mosquitto)
- Hari 5–6: A1 — OAuth server berjalan dan bisa mengeluarkan token
- Hari 7: A5 — `database/schema.sql` selesai dan bisa dijalankan

### Minggu 2 — Service Layer (Prioritas: A2, A3, A4)

- Hari 8–9: A2 — Traffic Service CRUD dan publish ke RabbitMQ
- Hari 8–9: A3 — Citizen Service CRUD selesai
- Hari 10–11: A2 — Sensor simulator + Mosquitto berjalan
- Hari 10–11: A3 — Parking Service CRUD selesai
- Hari 12–13: A4 — Dataset sintetis dan pelatihan model (R² ≥ 0.70)
- Hari 14: A1 — API Gateway routing + rate limiting selesai

### Minggu 3 — Integrasi (Semua)

- Hari 15–16: Integrasi S1 — IoT → Node-RED → Gateway → Traffic Service → RabbitMQ → ML
- Hari 17–18: Integrasi S2 — OAuth login → laporan → notifikasi
- Hari 19–20: Integrasi S3 — ML real-time prediction via Gateway
- Hari 21: A5 — Docker Compose full stack (semua container healthy) → S4

### Minggu 4 — Deployment & Finishing

- Hari 22–23: A5 — Kubernetes manifests + HPA → test S5
- Hari 24: Integrasi S6 — Anomaly alert flow end-to-end
- Hari 25: Test semua skenario di server (bukan lokal)
- Hari 26: A4 — EDA notebook dan ML Report lengkap
- Hari 27: Postman Collection + Diagram Arsitektur
- Hari 28: Rekam Demo Video di server
- Hari 29: README.md final + Daftar Kontribusi
- Hari 30: Buffer / perbaikan last-minute + submit

### Titik Sinkronisasi Tim (Meeting Wajib)

- **Akhir Minggu 1:** Review schema DB bersama, pastikan semua tabel sudah benar
- **Akhir Minggu 2:** Demo service masing-masing ke satu sama lain
- **Akhir Minggu 3:** Skenario S1–S4 harus sudah berjalan di lokal
- **Hari H-3:** Dry run seluruh demo di server

---

## 13. Aturan Server & Keamanan

### Akses Server

```bash
SSH: ssh -p 8989 mahasiswa@103.147.92.134
Username: kelompok[N]   # sesuaikan nomor kelompok
```

### Aturan yang WAJIB Dipatuhi

1. **JANGAN** simpan password, JWT secret, atau credential apapun di kode yang di-push ke Git
2. Selalu gunakan file `.env` — periksa ulang sebelum setiap `git push`
3. **DILARANG** mengakses atau membaca log service kelompok lain
4. **DILARANG** mematikan container atau pod yang bukan milik kelompok sendiri
5. Port yang boleh digunakan hanya yang sudah dialokasikan (lihat tabel di atas)
6. Hubungi dosen/asisten jika ada masalah server atau port konflik

### Verifikasi Keamanan Sebelum Push

```bash
# Pastikan .env tidak ikut di-staging
git status
# Jika .env muncul, jalankan:
git rm --cached .env

# Scan credential di kode sebelum push
grep -r "password\|secret\|token" --include="*.php" --include="*.js" --include="*.py" . \
  | grep -v ".env" | grep -v ".example" | grep -v ".gitignore"
```

---

*Dokumen ini dibuat sebagai panduan kolaborasi tim. Update sesuai perkembangan proyek.*

**Selamat berkarya dan eksplorasi!** 🚀
