# Smart Traffic & Parking Management System

**Platform manajemen lalu lintas dan parkir berbasis microservice** yang memonitor kepadatan lalu lintas dan ketersediaan parkir secara real-time menggunakan sensor IoT, memprediksi kemacetan dengan Machine Learning, dan memberikan notifikasi otomatis kepada warga saat terjadi anomali.

---

## 📋 Table of Contents

- [Arsitektur Sistem](#arsitektur-sistem)
- [Technology Stack](#technology-stack)
- [Prerequisites](#prerequisites)
- [Setup Lokal](#setup-lokal)
- [Daftar API Endpoints](#daftar-api-endpoints)
- [Authentication & Authorization](#authentication--authorization)
- [End-to-End Workflows](#end-to-end-workflows)
- [Running Locally](#running-locally)
- [Deployment](#deployment)
- [Monitoring & Debugging](#monitoring--debugging)
- [Troubleshooting](#troubleshooting)
- [Tim Pengembang](#tim-pengembang)

---

## 🏗️ Arsitektur Sistem

### 6-Layer Architecture Diagram

```
┌──────────────────────────────────────────────────────────┐
│  1. IoT LAYER                                            │
│  Sensor Simulator (Python) → Mosquitto MQTT → Node-RED   │
└──────────────────┬─────────────────────────────────────┘
                   │ HTTP POST
┌──────────────────▼─────────────────────────────────────┐
│  2. GATEWAY LAYER                                      │
│  API Gateway (Express.js :3000)  ←→  OAuth Server (:3002)│
└──────┬──────────────┬──────────────┬──────────────────┘
       │              │              │
┌──────▼────┐  ┌──────▼──┐  ┌──────▼──────────────────┐
│ CITIZEN    │  │ TRAFFIC  │  │ PARKING SERVICE        │
│ SERVICE    │  │ SERVICE  │  │ (PHP :8002)            │
│ (PHP :8000)│  │ (PHP :8001)  └────────────────────────┘
└──────┬────┘  └──────┬──┘
       │              │ publish
┌──────▼──────────────▼──────────────────────────────────┐
│  3. MESSAGING LAYER                                    │
│  RabbitMQ (:5672) — Queues: traffic.new, anomaly.alert │
└──────────────────┬─────────────────────────────────────┘
                   │ consume
┌──────────────────▼─────────────────────────────────────┐
│  4. ML LAYER                                           │
│  Python FastAPI (:5000)                                │
│  - Traffic Density Predictor  (Regression)            │
│  - Parking Occupancy Forecast (Regression)            │
│  - Anomaly Detector           (Isolation Forest)      │
└──────────────────┬─────────────────────────────────────┘
                   │
┌──────────────────▼─────────────────────────────────────┐
│  5. DATA LAYER                                         │
│  MySQL Database + Persistence Storage                 │
└────────────────────────────────────────────────────────┘
┌────────────────────────────────────────────────────────┐
│  6. INFRA LAYER                                        │
│  Docker Compose (dev) → Kubernetes (prod)             │
│  Prometheus (:9090) + Grafana (:3001) Monitoring      │
└────────────────────────────────────────────────────────┘
```

### Services & Ports

| Layer | Service | Port | Language | Technology | PIC |
|-------|---------|------|----------|-----------|-----|
| Gateway | API Gateway | 3000 | JavaScript | Express.js | Nadia |
| Gateway | OAuth Server | 3002 | JavaScript | Express.js + JWT | Nadia |
| Business | Citizen Service | 8000 | PHP | Laravel-like MVC | Ryan |
| Business | Traffic Service | 8001 | PHP | Laravel-like MVC | Fakhry |
| Business | Parking Service | 8002 | PHP | Laravel-like MVC | Ryan |
| ML | Python ML | 5000 | Python | FastAPI | Reza |
| Messaging | RabbitMQ | 5672 | - | AMQP Broker | Ryan |
| Messaging | Mosquitto | 1883 | - | MQTT Broker | Fakhry |
| Database | MySQL | 3306 | - | Relational DB | Nafisa |
| Monitoring | Prometheus | 9090 | - | Metrics | Nafisa |
| Monitoring | Grafana | 3001 | - | Dashboard | Nafisa |
| IoT | Node-RED | 1880 | - | Flow Editor | Fakhry |

---

## 💻 Technology Stack

- **API Gateway:** Express.js with JWT middleware & rate limiting
- **OAuth Server:** Express.js + jsonwebtoken + bcryptjs
- **Microservices:** PHP 8.2 with custom MVC pattern (no framework)
- **ML Service:** Python FastAPI + scikit-learn (RandomForest, IsolationForest, GradientBoosting)
- **Message Broker:** RabbitMQ 3.12 (AMQP)
- **IoT Broker:** Mosquitto 2.0 (MQTT)
- **IoT Flow Editor:** Node-RED 3.x
- **Database:** MySQL 8.0
- **Monitoring:** Prometheus + Grafana
- **Containerization:** Docker 24.x + Docker Compose v2
- **Orchestration:** Kubernetes 1.28+
- **Version Control:** Git with GitHub

---

## 📋 Prerequisites

| Tool | Version | Required For |
|------|---------|--------------|
| Docker Desktop | 24.x | All |
| Docker Compose | v2+ | All |
| Git | 2.x | All |
| Node.js | 18+ LTS | Gateway developers |
| PHP | 8.2+ | Service developers |
| Python | 3.11+ | ML developers |
| kubectl | 1.28+ | Deployment |
| MySQL Client | 8.0+ | Database migrations |
| curl/Postman | - | API testing |

---

## 🚀 Setup Lokal

### Langkah 1: Clone Repository

```bash
git clone https://github.com/Smart-Traffic-Parking-Management-System/backend-smart-parking.git
cd backend-smart-parking
```

### Langkah 2: Setup Environment

```bash
# Copy template environment
cp .env.example .env

# Edit .env dengan values lokal Anda
nano .env  # atau gunakan editor favorit

# Verifikasi .env tidak akan di-track
git status  # .env harus NOT muncul
```

### Langkah 3: Build & Run All Services

```bash
# Build dan jalankan semua container
docker compose up -d --build

# Tunggu semua container healthy (biasanya 30-60 detik)
docker compose ps

# Output yang diharapkan:
# STATUS: "Up X seconds (healthy)" untuk semua service
```

### Langkah 4: Initialize Database

```bash
# Setup schema
docker exec -i $(docker compose ps -q mysql) \
  mysql -u root -p${DB_ROOT_PASSWORD} smartcity < database/schema.sql

# Seed data awal
docker exec -i $(docker compose ps -q mysql) \
  mysql -u root -p${DB_ROOT_PASSWORD} smartcity < database/seed2.sql

# Verifikasi
docker exec -i $(docker compose ps -q mysql) \
  mysql -u root -p${DB_ROOT_PASSWORD} smartcity -e "SELECT COUNT(*) FROM zones;"
```

### Langkah 5: Verify System

```bash
# Health check semua service
curl -s http://localhost:3000/health | jq '.'

# Contoh response:
# {
#   "status": "success",
#   "code": 200,
#   "data": {
#     "gateway": "healthy",
#     "citizen_service": "healthy",
#     "traffic_service": "healthy",
#     "parking_service": "healthy",
#     "python_ml": "healthy",
#     "mysql": "healthy"
#   },
#   ...
# }
```

---

## 📡 Daftar API Endpoints

### Authentication (via Gateway :3000)

**New JWT-Based Authentication** (OAuth refactored to pure JWT)

Semua authentication endpoints diakses melalui **Gateway port 3000** untuk konsistensi dengan service lain. Gateway secara otomatis meroute ke OAuth Server internal.

| Method | Endpoint | Body | Response | Status |
|--------|----------|------|----------|--------|
| **POST** | `/oauth/register` | `{username, email, password}` | User object + tokens | 201 |
| **POST** | `/oauth/login` | `{username, password}` | `{access_token, refresh_token, expires_in}` | 200 |
| **POST** | `/oauth/refresh` | `{refresh_token}` | `{access_token, expires_in}` | 200 |
| **POST** | `/oauth/revoke` | `{token}` | `{status: "success"}` | 200 |
| **POST** | `/oauth/introspect` | `{token}` | `{active, user_id, role, exp}` | 200 |
| **POST** | `/oauth/service-token` | `{service_name}` | `{access_token, token_type, expires_in}` | 200 |
| **GET** | `/oauth/google` | - | Redirect to Google OAuth | 302 |
| **GET** | `/oauth/google/callback` | - | Handle Google callback | 302 |
| **GET** | `/oauth/debug/users` | - | List all users (dev only) | 200 |
| **GET** | `/oauth/debug/ping` | - | Service status (dev only) | 200 |

**Example Request:**
```bash
# Register (via gateway)
curl -X POST http://localhost:3000/oauth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "budi_santoso",
    "email": "budi@example.com",
    "password": "securepass123"
  }'

# Login (via gateway)
curl -X POST http://localhost:3000/oauth/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "admin@123"}'
```

---

### Gateway (:3000)

**All authentication & API endpoints are routed through gateway for consistency**

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| **POST** | `/oauth/register` | None | User registration |
| **POST** | `/oauth/login` | None | User login |
| **POST** | `/oauth/refresh` | None | Refresh access token |
| **POST** | `/oauth/revoke` | JWT | Logout / revoke token |
| **POST** | `/oauth/introspect` | Internal | Verify token status (called by gateway) |
| **GET** | `/oauth/google` | None | Google OAuth redirect |
| **GET** | `/oauth/google/callback` | None | Google OAuth callback |
| **GET** | `/health` | None | Aggregated health status of all services |
| **GET** | `/metrics` | Internal | Prometheus scrape endpoint |
| **POST** | `/iot/traffic` | API Key | IoT sensor data ingestion |
| **POST** | `/iot/parking` | API Key | Parking occupancy update |

---

### Citizen Service (:8000, via Gateway `/api/citizens`)

| Method | Endpoint | Auth | Deskripsi | Response |
|--------|----------|------|-----------|----------|
| **POST** | `/api/citizens` | None | Register warga baru | `{id, username, email, role, created_at}` |
| **GET** | `/api/citizens/:id` | JWT | Profil warga | `{id, name, email, phone, zone}` |
| **PUT** | `/api/citizens/:id` | JWT | Update profil | Updated user object |
| **POST** | `/api/reports` | JWT | Submit laporan | `{id, category, zone, status, created_at}` |

> Catatan: `POST /api/citizens` mengembalikan objek `data.citizen` yang berisi `id`. Gunakan nilai `data.citizen.id` tersebut sebagai parameter `:id` untuk `PUT /api/citizens/:id`.
| **GET** | `/api/reports` | JWT | Daftar laporan (filter: status, zone) | Array of reports |
| **PATCH** | `/api/reports/:id/status` | JWT+Admin | Update status laporan | Updated report |
| **GET** | `/api/notifications` | JWT | Notifikasi user yang login | Array of notifications |
| **PATCH** | `/api/notifications/:id/read` | JWT | Tandai sudah dibaca | Updated notification |
| **GET** | `/health` | None | DB connection status | `{status, database}` |

**Example:**
```bash
# Submit Report
curl -X POST http://localhost:3000/api/reports \
  -H "Authorization: Bearer <access_token>" \
  -H "Content-Type: application/json" \
  -d '{
    "category": "kemacetan",
    "zone_id": 1,
    "description": "Kemacetan parah di Jl. Sudirman"
  }'
```

**Body Request Dummy:**

**POST /api/citizens**
```json
{
  "username": "budi_santoso",
  "email": "budi@example.com",
  "password": "SecurePass123!",
  "full_name": "Budi Santoso",
  "phone": "081234567890",
  "zone_id": 2
}
```

**PUT /api/citizens/:id**
```json
{
  "full_name": "Budi Santoso",
  "phone": "081234567890",
  "zone_id": 3,
  "email": "budi.updated@example.com"
}
```

**POST /api/reports**
```json
{
  "category": "kecelakaan",
  "zone_id": 4,
  "description": "Kecelakaan ringan di simpang lampu lalu lintas",
  "severity": "medium"
}
```

**PATCH /api/reports/:id/status**
```json
{
  "status": "in_progress"
}
```

**PATCH /api/notifications/:id/read**
```json
{
  "read": true
}
```

---

### Traffic Service (:8001, via Gateway `/api/traffic`)

| Method | Endpoint | Auth | Deskripsi | Status |
|--------|----------|------|-----------|--------|
| **POST** | `/api/traffic/readings` | IoT/JWT | Simpan sensor data → publish RabbitMQ | 201 ✅ |
| **GET** | `/api/traffic/current` | JWT | Status real-time per zona | 200 ✅ |
| **GET** | `/api/traffic/history` | JWT | Riwayat (filter: date, zone) | 200 ✅ |
| **GET** | `/api/roads` | JWT | Daftar segmen jalan | 200 ✅ |
| **POST** | `/api/incidents` | JWT | Laporkan insiden | 201 ✅ |
| **GET** | `/api/incidents` | JWT | Daftar insiden aktif | 200 ✅ |
| **PATCH** | `/api/incidents/:id/resolve` | JWT+Admin | Selesaikan insiden | 200 ✅ |
| **GET** | `/health` | None | DB status | 200 ✅ |

---

**Body Request Dummy:**

**POST /api/traffic/readings**
```json
{
  "sensor_id": "sensor-701",
  "zone_id": 5,
  "vehicle_count": 42,
  "average_speed": 32.5,
  "timestamp": "2026-06-29T08:45:00Z"
}
```

**POST /api/incidents**
```json
{
  "zone_id": 5,
  "type": "kendaraan mogok",
  "description": "Bus berhenti di lajur cepat, mengganggu arus lalu lintas",
  "severity": "high",
  "reported_by": "operator"
}
```

**PATCH /api/incidents/:id/resolve**
```json
{
  "status": "resolved",
  "resolved_by": "admin",
  "notes": "Kendaraan telah dipindahkan dan lalu lintas normal kembali"
}
```

---

### Parking Service (:8002, via Gateway `/api/parking`)

| Method | Endpoint | Auth | Deskripsi | Status |
|--------|----------|------|-----------|--------|
| **GET** | `/api/parking/zones` | JWT | Daftar area parkir | 200 ✅ |
| **GET** | `/api/parking/slots` | JWT | Slot tersedia (filter: zone) | 200 ✅ |
| **GET** | `/api/parking/slots/:id` | JWT | Detail slot individual | 200 ✅ |
| **POST** | `/api/parking/reserve` | JWT | Reservasi slot | 201 ✅ |
| **PATCH** | `/api/parking/checkin/:id` | JWT | Check-in | 200 ✅ |
| **PATCH** | `/api/parking/checkout/:id` | JWT | Check-out + hitung durasi | 200 ✅ |
| **GET** | `/api/parking/history` | JWT | Riwayat parkir user | 200 ✅ |
| **GET** | `/health` | None | DB status | 200 ✅ |

---

**Body Request Dummy:**

**POST /api/parking/reserve**
```json
{
  "slot_id": 12,
  "citizen_id": 7,
  "vehicle_plate": "B 1234 CD",
  "start_time": "2026-06-29T10:00:00Z",
  "estimated_duration_hours": 2
}
```

**PATCH /api/parking/checkin/:id**
```json
{
  "vehicle_plate": "B 1234 CD",
  "entry_method": "manual",
  "checkin_time": "2026-06-29T10:05:00Z"
}
```

**PATCH /api/parking/checkout/:id**
```json
{
  "checkout_time": "2026-06-29T12:10:00Z",
  "payment_method": "mobile_wallet",
  "notes": "Checkout melalui aplikasi"
}
```

---

### Python ML Service (:5000, via Gateway `/predict`, `/detect`)

| Method | Endpoint | Auth | Deskripsi | Status |
|--------|----------|------|-----------|--------|
| **GET** | `/health` | None | Service + model status | 200 ✅ |
| **POST** | `/predict/traffic` | JWT | Prediksi kepadatan (latency < 500ms) | 200 ✅ |
| **POST** | `/predict/parking` | JWT | Prediksi occupancy parkir | 200 ✅ |
| **POST** | `/detect/anomaly` | JWT | Deteksi anomali sensor | 200 ✅ |
| **GET** | `/model/feature-importance` | JWT | Bobot fitur ketiga model | 200 ✅ |
| **POST** | `/predict/batch` | JWT | Batch prediction (array) | 200 ✅ |

**Body Request Dummy:**

**POST /predict/traffic**
```json
{
  "sensor_id": "sensor-007",
  "zone_id": 5,
  "vehicle_count": 38,
  "average_speed": 29.4,
  "timestamp": "2026-06-29T09:15:00Z"
}
```

**POST /predict/parking**
```json
{
  "zone_id": 2,
  "available_slots": 15,
  "vehicle_count": 23,
  "timestamp": "2026-06-29T09:20:00Z"
}
```

**POST /detect/anomaly**
```json
{
  "sensor_id": "sensor-010",
  "zone_id": 5,
  "vehicle_count": 180,
  "average_speed": 8.7,
  "timestamp": "2026-06-29T09:25:00Z"
}
```

**POST /predict/batch**
```json
{
  "records": [
    {
      "sensor_id": "sensor-007",
      "zone_id": 5,
      "vehicle_count": 38,
      "average_speed": 29.4,
      "timestamp": "2026-06-29T09:15:00Z"
    },
    {
      "sensor_id": "sensor-010",
      "zone_id": 2,
      "vehicle_count": 12,
      "average_speed": 42.1,
      "timestamp": "2026-06-29T09:18:00Z"
    }
  ]
}
```

**Response Format Standard** (All endpoints):
```json
{
  "status": "success",
  "code": 200,
  "data": { /* payload */ },
  "message": "Human-readable message",
  "timestamp": "2026-01-15T10:30:00.000Z",
  "service": "service-name"
}
```

---

## 🔐 Authentication & Authorization

### JWT Token Structure

```json
Header:
{
  "alg": "HS256",
  "typ": "JWT"
}

Payload:
{
  "user_id": 1,
  "username": "admin",
  "email": "admin@smartcity.local",
  "role": "admin|citizen",
  "iat": 1642244400,
  "exp": 1642248000
}
```

### Token Lifecycle

1. **Registration** → POST `http://localhost:3000/oauth/register` (via gateway) → User created with `role: citizen`
2. **Login** → POST `http://localhost:3000/oauth/login` (via gateway) → Get `access_token` (1 hour TTL) + `refresh_token` (24 hour TTL)
3. **API Usage** → Include header in any request: `Authorization: Bearer <access_token>`
4. **Token Expires** → POST `http://localhost:3000/oauth/refresh` (via gateway) with `refresh_token` → Get new `access_token`
5. **Logout** → POST `http://localhost:3000/oauth/revoke` (via gateway) → Token added to blacklist
6. **Verification** → Gateway intercepts request, validates token using `/oauth/introspect` → Returns 401 if invalid or expired

### Default Admin User

Satu admin user di-initialize otomatis saat OAuth server start:

```env
ADMIN_USERNAME=admin
ADMIN_EMAIL=admin@smartcity.local
ADMIN_PASSWORD=admin@123
```

### Rate Limiting

- **Global:** 100 requests / 15 minutes per IP → 429 Too Many Requests
- **Authenticated:** 500 requests / hour per token → 429 Too Many Requests

---

## 🔄 End-to-End Workflows

### Workflow 1: Citizen Self-Service (Register → Report → Notification)

```
1. Citizen registers
   POST /oauth/register
   ↓
2. Receives access_token & refresh_token
   ↓
3. Uses token to submit traffic report
   POST /api/reports (header: Bearer <token>)
   ↓
4. Report published to RabbitMQ (report.submitted)
   ↓
5. System detects congestion via ML
   ↓
6. Anomaly alert published (anomaly.alert)
   ↓
7. Notification Consumer receives alert
   ↓
8. Citizen sees notification on dashboard
   GET /api/notifications (header: Bearer <token>)
```

### Workflow 2: IoT Data Ingestion (Sensor → Prediction → Alert)

```
Sensor Simulator (Python)
  ↓ MQTT publish (every 30s)
Mosquitto MQTT Broker (city/zone1/traffic)
  ↓ subscribe
Node-RED Flow
  ↓ HTTP POST
API Gateway (/iot/traffic)
  ↓ forward
Traffic Service
  ↓ POST /api/traffic/readings
MySQL (traffic_readings table)
  + RabbitMQ publish (traffic.new)
  ↓
Python ML Service
  ↓ consume (traffic_consumer.py)
  ↓ predict & detect anomaly
RabbitMQ publish (anomaly.alert)
  ↓
Citizen Service (Notification Consumer)
  ↓ create notification
MySQL (notifications table)
  ↓
Citizen GET /api/notifications → sees alert
```

### Workflow 3: Parking Reservation (Search → Reserve → Checkin → Checkout)

```
1. User queries available slots
   GET /api/parking/slots?zone_id=1
   
2. Selects slot and reserves
   POST /api/parking/reserve
   {slot_id: 5}
   
3. Arrives at parking lot and checks in
   PATCH /api/parking/checkin/:reservation_id
   
4. Parks vehicle
   
5. Leaves and checks out
   PATCH /api/parking/checkout/:reservation_id
   ↓
   System calculates:
   - duration = checkout_time - checkin_time
   - cost = duration * rate_per_hour
   - updates parking_slots status to 'available'
   
6. User views history
   GET /api/parking/history
   ↓ shows all past reservations + cost
```

---

## ▶️ Running Locally

### Jalankan Semua Service

```bash
# Terminal 1: Start all containers
docker compose up -d --build
docker compose ps  # verify all healthy

# Terminal 2: Monitor logs
docker compose logs -f

# Terminal 3: Test endpoints
curl http://localhost:3000/health
```

### Common Commands

```bash
# View logs specific service
docker compose logs -f traffic-service
docker compose logs -f python-ml --tail 100

# Restart service after code change
docker compose up -d --build traffic-service

# Stop all services
docker compose down

# Stop and remove volumes (reset data)
docker compose down -v

# SSH into container
docker exec -it smartcity-traffic-service bash

# Run database query
docker exec -i $(docker compose ps -q mysql) \
  mysql -u smartcity_app -ppassword smartcity \
  -e "SELECT * FROM zones;"

# View RabbitMQ Management UI
open http://localhost:15672  # username: guest, password: guest

# View Grafana Dashboard
open http://localhost:3001   # username: admin, password: admin
```

### Testing API Endpoints

**Using Postman:**
1. Import file: `postman/SmartTraffic.postman_collection.json`
2. Set environment variable: `{{baseUrl}} = http://localhost:3000` (Gateway)
3. Workflow:
   ```
   1. POST /oauth/register → Create account
   2. POST /oauth/login → copy access_token
   3. Set Bearer token in Postman auth (top right dropdown → Bearer Token)
   4. Test any endpoints (all go through gateway)
   ```

**Using curl:**
```bash
# 1. Register new user (gateway routes to OAuth server)
curl -X POST http://localhost:3000/oauth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "john_doe",
    "email": "john@example.com",
    "password": "password123"
  }'

# 2. Login & get token (gateway routes to OAuth server)
TOKEN=$(curl -s -X POST http://localhost:3000/oauth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin@123"}' \
  | jq -r '.data.access_token')

# 3. Use token in any API request to gateway
curl -X GET http://localhost:3000/api/citizens \
  -H "Authorization: Bearer $TOKEN"

# 4. Submit report via gateway
curl -X POST http://localhost:3000/api/reports \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "category": "kemacetan",
    "zone_id": 1,
    "description": "Kemacetan parah"
  }'

# 5. Refresh token when expired (gateway routes to OAuth server)
REFRESH_TOKEN=$(curl -s -X POST http://localhost:3000/oauth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin@123"}' \
  | jq -r '.data.refresh_token')

NEW_TOKEN=$(curl -s -X POST http://localhost:3000/oauth/refresh \
  -H "Content-Type: application/json" \
  -d "{\"refresh_token\":\"$REFRESH_TOKEN\"}" \
  | jq -r '.data.access_token')

# 6. Logout (revoke token via gateway)
curl -X POST http://localhost:3000/oauth/revoke \
  -H "Content-Type: application/json" \
  -d "{\"token\":\"$TOKEN\"}"
```

---

## 📦 Deployment

### Deploy dengan Docker Compose (Development)

```bash
# Already running locally
docker compose up -d
```

### Deploy ke Kubernetes (Production)

```bash
# 1. Create namespace
kubectl create namespace smartcity

# 2. Apply all manifests
kubectl apply -f k8s/ -n smartcity

# 3. Verify deployment
kubectl get pods -n smartcity
kubectl describe pod <pod-name> -n smartcity

# 4. Check service status
kubectl get svc -n smartcity

# 5. Get external IP
kubectl get ingress -n smartcity

# 6. Monitor HPA
kubectl get hpa -n smartcity -w

# 7. View logs
kubectl logs <pod-name> -n smartcity -f

# 8. Scale replicas manually
kubectl scale deployment python-ml --replicas=3 -n smartcity

# 9. Update image (rolling update)
kubectl set image deployment/traffic-service \
  traffic-service=registry.example.com/traffic-service:v2 \
  -n smartcity

# 10. Rollback if needed
kubectl rollout undo deployment/traffic-service -n smartcity
```

### Environment Configuration

**Development (.env):**
```env
NODE_ENV=development
DB_HOST=mysql
DB_PORT=3306
ADMIN_USERNAME=admin
ADMIN_PASSWORD=admin@123
JWT_SECRET=dev-secret-key
ADMIN_EMAIL=admin@smartcity.local
```

**Production (Kubernetes secrets):**
```bash
kubectl create secret generic smartcity-secrets \
  --from-literal=DB_PASSWORD=prod-password \
  --from-literal=JWT_SECRET=prod-secret-key \
  -n smartcity
```

---

## 📊 Monitoring & Debugging

### Prometheus Metrics

Access: http://localhost:9090

**Key Metrics:**
- `http_requests_total{service}` — Total requests per service
- `http_request_duration_seconds` — Response time (P95)
- `container_cpu_usage_seconds_total` — CPU usage per container
- `container_memory_usage_bytes` — Memory usage per container

### Grafana Dashboard

Access: http://localhost:3001 (admin / admin)

**Dashboards:**
1. **System Overview** — CPU, Memory, Network per service
2. **API Metrics** — Request rate, error rate, latency
3. **RabbitMQ Queue Depth** — Message backlog per queue
4. **Database Performance** — Query count, connection pool

### View Logs

```bash
# All services
docker compose logs -f

# Specific service
docker compose logs -f oauth-server
docker compose logs -f python-ml

# Last N lines
docker compose logs --tail 100 traffic-service

# Since specific time
docker compose logs --since 10m python-ml
```

### Database Queries

```bash
# Connect to MySQL
docker exec -it $(docker compose ps -q mysql) \
  mysql -u smartcity_app -ppassword smartcity

# Common queries
SELECT COUNT(*) FROM traffic_readings;
SELECT * FROM zones;
SELECT * FROM citizens WHERE role='admin';
SELECT * FROM notifications WHERE is_read=0;
```

### RabbitMQ Management

Access: http://localhost:15672 (guest / guest)

**Check:**
- Active connections
- Queue depth (messages waiting)
- Dead-letter exchanges

---

## 🔧 Troubleshooting

### Container Health Issues

**Problem:** Container not healthy
```bash
# Check status
docker compose ps

# View health check logs
docker inspect <container-id> | grep -A 20 "Health"

# Manually test
docker exec <container> curl localhost:PORT/health
```

**Solution:**
```bash
# Restart container
docker compose restart <service>

# Rebuild and restart
docker compose up -d --build <service>

# Check resource limits
docker stats <service>
```

### Database Connection Error

**Problem:** `Connection refused` to MySQL
```bash
# Verify container running
docker compose ps mysql

# Check logs
docker compose logs mysql

# Test connection
docker exec $(docker compose ps -q mysql) \
  mysql -u root -proot123 -e "SELECT 1;"
```

**Solution:**
```bash
# Restart MySQL
docker compose down mysql
docker compose up -d mysql
docker compose ps  # wait for healthy

# Reinit database
docker exec -i $(docker compose ps -q mysql) \
  mysql -u root -proot123 smartcity < database/schema.sql
```

### Port Already in Use

**Problem:** `Address already in use` error
```bash
# Find process using port
lsof -i :3000  # macOS/Linux
netstat -ano | findstr :3000  # Windows

# Kill process
kill -9 <PID>  # macOS/Linux
taskkill /PID <PID> /F  # Windows
```

### Rate Limiting Issue

**Problem:** Getting 429 Too Many Requests
```bash
# Check rate limit headers
curl -I http://localhost:3000/health | grep -i rate

# Solution: Use refresh_token to get new access_token
POST /oauth/refresh {refresh_token}
```

### RabbitMQ Connection Error

**Problem:** Services can't connect to RabbitMQ
```bash
# Verify RabbitMQ running
docker compose ps rabbitmq

# Check logs
docker compose logs rabbitmq

# Test connection
docker exec $(docker compose ps -q rabbitmq) \
  rabbitmq-diagnostics -q ping
```

### Python ML Model Not Loading

**Problem:** `/health` returns model not loaded
```bash
# Check if model file exists
ls -la python-ml-service/models/

# Train model
docker exec $(docker compose ps -q python-ml) \
  python train_models.py

# Restart service
docker compose restart python-ml
```

---

## 📁 Struktur Repository

```
backend-smart-parking/
│
├── 📄 README.md                    ← You are here
├── 📄 Makefile                     ← Shortcut commands
├── 📄 docker-compose.yml           ← Local development stack
├── 📄 .env.example                 ← Template env variables
│
├── 📁 express-gateway/             # API Gateway + Rate Limiting
│   ├── src/
│   │   ├── index.js
│   │   ├── middleware/
│   │   │   ├── jwt.js
│   │   │   ├── rateLimit.js
│   │   │   └── logger.js
│   │   ├── routes/
│   │   │   └── proxy.js
│   │   └── utils/
│   │       └── healthCheck.js
│   └── Dockerfile
│
├── 📁 oauth-server/                # OAuth 2.0 + JWT Server
│   ├── src/
│   │   ├── index.js
│   │   ├── models/
│   │   │   ├── user.js             ✨ NEW: Flexible user management
│   │   │   └── token.js            ✨ REFACTORED: JWT-focused
│   │   ├── routes/
│   │   │   └── oauth.js            ✨ REFACTORED: 7 endpoints
│   │   └── middleware/
│   ├── AUTHENTICATION.md           ✨ NEW: API documentation
│   ├── REFACTOR_SUMMARY.md         ✨ NEW: Migration guide
│   └── Dockerfile
│
├── 📁 php-citizen/                 # Citizen Service (PHP MVC)
│   ├── app/
│   │   ├── Controllers/
│   │   │   ├── CitizenController.php
│   │   │   ├── ReportController.php
│   │   │   └── NotifController.php
│   │   ├── Models/
│   │   └── Services/
│   ├── config/
│   └── public/index.php
│
├── 📁 php-traffic/                 # Traffic Service (PHP MVC)
│   ├── app/
│   │   ├── Controllers/
│   │   │   ├── TrafficController.php
│   │   │   ├── RoadController.php
│   │   │   └── IncidentController.php
│   │   ├── Models/
│   │   └── Services/
│   └── public/index.php
│
├── 📁 php-parking/                 # Parking Service (PHP MVC)
│   ├── app/
│   │   ├── Controllers/
│   │   │   ├── ParkingController.php
│   │   │   └── ReservationController.php
│   │   ├── Models/
│   │   └── Services/
│   └── public/index.php
│
├── 📁 python-ml-service/           # ML Service (FastAPI)
│   ├── main.py
│   ├── train_models.py
│   ├── consumers/
│   │   ├── traffic_consumer.py
│   │   └── anomaly_publisher.py
│   ├── models/                     # .pkl files (in .gitignore)
│   ├── data/
│   │   ├── traffic_history.csv
│   │   └── parking_history.csv
│   └── requirements.txt
│
├── 📁 iot/                         # IoT Layer
│   ├── simulator.py                # Sensor data generator
│   ├── mosquitto.conf
│   ├── rabbitmq-init.sh
│   └── node-red-data/
│       ├── flows.json
│       └── settings.js
│
├── 📁 database/                    # Database Schema
│   ├── schema.sql                  # CREATE TABLE statements
│   └── seed.sql                    # Initial data
│
├── 📁 k8s/                         # Kubernetes Manifests
│   ├── namespace.yaml
│   ├── configmap.yaml
│   ├── secrets.yaml
│   ├── mysql-statefulset.yaml
│   ├── rabbitmq-deployment.yaml
│   ├── gateway-deployment.yaml
│   ├── python-ml-deployment.yaml
│   └── ingress.yaml
│
├── 📁 monitoring/                  # Prometheus + Grafana
│   ├── prometheus.yml
│   └── grafana-dashboard.json
│
├── 📁 docs/                        # Documentation
│   ├── plan.md                     # Full project specification
│   ├── AUTHENTICATION.md
│   ├── DEPLOYMENT.md
│   └── architecture-diagram.png
│
└── 📁 postman/                     # API Testing
    ├── Auth.postman_collection.json
    └── SmartTraffic.postman_collection.json
```

---

## 👥 Tim Pengembang

| Nama | Tanggung Jawab | Kontak |
|------|---|---|
| **Nadia** | API Gateway + OAuth Server | Nadia@example.com |
| **Fakhry** | Traffic Service + IoT Layer | Fakhry@example.com |
| **Ryan** | Citizen Service + Parking Service | Ryan@example.com |
| **Reza** | Python ML Service | Reza@example.com |
| **Nafisa** | DevOps + Infrastructure | Nafisa@example.com |

---

## 📚 Documentation References

- [Detailed API Specification](docs/plan.md)
- [Authentication Guide](oauth-server/AUTHENTICATION.md)
- [OAuth Refactoring Summary](oauth-server/REFACTOR_SUMMARY.md)
- [Deployment Guide](docs/DEPLOYMENT.md)
- [Database Schema](database/schema.sql)

---

## 📞 Support & Issues

- **Bug Report:** Create GitHub issue
- **Feature Request:** Create GitHub discussion
- **Quick Help:** Check [Troubleshooting](#troubleshooting) section

---

**Last Updated:** January 2026 | Project Status: In Development | Test Coverage: 37/38 endpoints ✅