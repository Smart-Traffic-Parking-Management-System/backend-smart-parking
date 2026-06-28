# Authentication & Authorization (Admin / Citizens)

Dokumentasi ini menjelaskan alur authentication di proyek, format request yang didukung, cara membedakan admin dan citizen, serta contoh pengujian endpoint lengkap.

## Ringkasan singkat
- API Gateway: `http://localhost:3000`.
- Semua request ke `/oauth/*` pada gateway akan diproxy ke OAuth server.
- OAuth server mengeluarkan:
  - User token (JWT) untuk `password` grant; payload berisi `role` (`citizen`|`admin`) dan `user_id`/`citizen_id`.
  - Service token untuk `client_credentials` grant; token ini diproses menggunakan introspection dan memiliki `scope: 'service'`.

## Siapa admin / siapa citizen?
- `oauth-server` menentukan role saat token dibuat pada `POST /oauth/token`.
- `password` grant membaca pengguna dari in-memory user store:
  - Default admin: `username=admin`, `password=admin123`, `role=admin`.
  - Default citizen: `username=warga1`, `password=warga123`, `role=citizen`.
- Jika login berhasil, OAuth server membuat JWT dengan claim:
  - `role`: `admin` atau `citizen`
  - `user_id`: id numerik user
  - `citizen_id`: alias `user_id`
  - `username`
- Gateway memverifikasi JWT dan memakai `role` untuk menentukan akses endpoint.

## Token endpoints dan cara pengujian

### 1) `POST /oauth/token`
Endpoint ini meminta token. Semua request dikirim ke gateway pada `http://localhost:3000/oauth/token`.

#### a. Password grant (user / admin)
- Method: `POST`
- Content-Type: `application/x-www-form-urlencoded`
- Body:
  - `grant_type=password`
  - `username=<username>`
  - `password=<password>`

Contoh admin:
```bash
curl -X POST http://localhost:3000/oauth/token \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'grant_type=password&username=admin&password=admin123'
```

Contoh citizen:
```bash
curl -X POST http://localhost:3000/oauth/token \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'grant_type=password&username=warga1&password=warga123'
```

Response sukses:
```json
{
  "access_token": "<JWT>",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "<refresh_token>",
  "scope": "read write"
}
```

#### b. Client credentials grant (service token)
- Method: `POST`
- Content-Type: `application/x-www-form-urlencoded`
- Body:
  - `grant_type=client_credentials`
  - `client_id=smartcity_client`
  - `client_secret=e99eb63167bbda4d2fb6b4a849bfa41ef5761829f2b3585e6803921e8556c7f3`

Contoh:
```bash
curl -X POST http://localhost:3000/oauth/token \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'grant_type=client_credentials&client_id=smartcity_client&client_secret=e99eb63167bbda4d2fb6b4a849bfa41ef5761829f2b3585e6803921e8556c7f3'
```

Atau menggunakan Basic auth:
```bash
curl -X POST http://localhost:3000/oauth/token \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -H 'Authorization: Basic c21hcnRjaXR5X2NsaWVudDplOTllYjYzMTY3YmJkYTRkMmZiNmI0YTg0OWJmYTQxZWY1NzYxODI5ZjJiMzU4NWU2ODAzOTJlODU1NmM3ZjM=' \
  -d 'grant_type=client_credentials'
```

Response sukses service token:
```json
{
  "access_token": "<JWT>",
  "token_type": "Bearer",
  "expires_in": 3600,
  "scope": "service"
}
```

#### c. Refresh token
- Method: `POST`
- Content-Type: `application/x-www-form-urlencoded`
- Body:
  - `grant_type=refresh_token`
  - `refresh_token=<refresh_token>`

Contoh:
```bash
curl -X POST http://localhost:3000/oauth/token \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'grant_type=refresh_token&refresh_token=<refresh_token>'
```

Response sukses:
```json
{
  "access_token": "<new JWT>",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "<same refresh_token>",
  "scope": "read write"
}
```

### 2) `POST /oauth/introspect`
Endpoint ini tidak untuk login pengguna biasa; ini untuk memeriksa token yang dikirim ke gateway atau service.

- Method: `POST`
- Content-Type: `application/json`
- Header: `x-api-key: SmartCity@Introspect#2026`
- Body:
  - `token=<access_token>`

Contoh:
```bash
curl -X POST http://localhost:3000/oauth/introspect \
  -H 'Content-Type: application/json' \
  -H 'x-api-key: SmartCity@Introspect#2026' \
  -d '{"token":"<access_token>"}'
```

Response sukses:
```json
{
  "active": true,
  "sub": 5,
  "user_id": 5,
  "citizen_id": 5,
  "username": "admin",
  "role": "admin",
  "scope": "read write"
}
```

Jika API key salah atau tidak dikirim, akan muncul `403 Invalid introspection API key`.

### 3) `POST /oauth/revoke`
- Method: `POST`
- Content-Type: `application/json`
- Body:
  - `token=<access_token>`

Contoh:
```bash
curl -X POST http://localhost:3000/oauth/revoke \
  -H 'Content-Type: application/json' \
  -d '{"token":"<access_token>"}'
```

Response sukses:
```json
{ "status": "success", "message": "Token revoked successfully" }
```

### 4) Google OAuth (jika dikonfigurasi)
- `GET /oauth/google` → redirect ke Google OAuth.
- `GET /oauth/google/callback` → callback Google mengembalikan token internal.

> Catatan: Google OAuth hanya bekerja jika `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, dan `GOOGLE_CALLBACK_URL` sudah di-set di env.

## Perbedaan auth admin vs citizen

### Admin
- Login dengan `grant_type=password` menggunakan:
  - `username=admin`
  - `password=admin123`
- Token yang dikembalikan berisi claim `role=admin`.
- Hanya admin yang bisa memanggil endpoint yang dikhususkan untuk admin seperti:
  - `PATCH /api/reports/:id/status`
  - `PATCH /api/incidents/:id/resolve`
  - `GET /model/feature-importance`
  - `POST /predict/batch`

### Citizen
- Login dengan `grant_type=password` menggunakan:
  - `username=warga1`
  - `password=warga123`
- Token yang dikembalikan berisi claim `role=citizen`.
- Citizen dapat mengakses endpoint umum pengguna seperti:
  - `GET /api/citizens/:id`
  - `PUT /api/citizens/:id`
  - `POST /api/reports`
  - `GET /api/reports`
  - `GET /api/notifications`
  - `GET /api/traffic/current`
  - `GET /api/parking/zones`
  - `POST /api/parking/reserve`

### Bagaimana gateway memutuskan
- Untuk endpoint `CITIZEN + ADMIN`, gateway menjamin JWT valid dan `role` adalah `citizen` atau `admin`.
- Untuk endpoint `ADMIN only`, gateway mengecek `role === 'admin'`.
- Untuk endpoint `SERVICE only`, gateway tidak menggunakan `role`; ia mengecek token service melalui introspection dan memastikan `scope === 'service'`.
- Untuk endpoint `ADMIN + SERVICE`, gateway menerima:
  - admin JWT (role=admin), atau
  - service token (scope=service) melalui introspection.

## JWT payload yang ditandai admin/citizen
Setelah login password grant, JWT payload biasanya berisi:
```json
{
  "sub": 5,
  "user_id": 5,
  "citizen_id": 5,
  "username": "admin",
  "role": "admin",
  "iat": 1690000000,
  "exp": 1690003600
}
```

Untuk citizen token, `role` akan bernilai `citizen` dan `citizen_id` akan menunjukkan id user warga.

## Contoh penggunaan token di endpoint API
Semua protected endpoint memakai header:
```http
Authorization: Bearer <access_token>
```

Contoh `GET /api/reports` sebagai citizen/admin:
```bash
curl -X GET http://localhost:3000/api/reports \
  -H 'Authorization: Bearer <access_token>'
```

Contoh `POST /detect/anomaly` sebagai admin atau service token:
```bash
curl -X POST http://localhost:3000/detect/anomaly \
  -H 'Authorization: Bearer <access_token>' \
  -H 'Content-Type: application/json' \
  -d '{"data": {}}'
```

## Matriks akses endpoint → role
Berikut ringkasan endpoint utama dan siapa yang boleh mengakses.

### PUBLIC (tanpa auth)
- `GET /health`
- `GET /` (gateway info)
- `POST /api/citizens` (register user baru)

### SERVICE only (client_credentials token dengan `scope=service`)
- `GET /metrics`
- `POST /iot/traffic`
- `POST /iot/parking`

### CITIZEN + ADMIN (JWT dengan `role=citizen|admin`)
- `GET /api/citizens/:id`
- `PUT /api/citizens/:id`
- `POST /api/reports`
- `GET /api/reports`
- `GET /api/notifications`
- `PATCH /api/notifications/:id/read`
- `GET /api/traffic/current`
- `GET /api/traffic/history`
- `GET /api/roads`
- `POST /api/incidents`
- `GET /api/incidents`
- `POST /predict/traffic`
- `POST /predict/parking`
- `GET /api/parking/zones`
- `GET /api/parking/slots`
- `POST /api/parking/reserve`
- `PATCH /api/parking/checkin/:id`
- `PATCH /api/parking/checkout/:id`
- `GET /api/parking/history`

### ADMIN only (JWT `role=admin`)
- `PATCH /api/reports/:id/status`
- `PATCH /api/incidents/:id/resolve`
- `GET /model/feature-importance`
- `POST /predict/batch`

### ADMIN + SERVICE
- `POST /detect/anomaly`
- `GET /model/feature-importance`
- `POST /predict/batch`

## Debug dan validasi tambahan
- `GET /oauth/debug/ping` — periksa OAuth server hidup (hanya `NODE_ENV=development`).
- `GET /oauth/debug/tokens` — lihat token aktif di store in-memory (development only).

## Tips penting
- Jika `grant_type is required`, pastikan body `POST /oauth/token` dalam bentuk `x-www-form-urlencoded`.
- Browser/Postman sukses menggunakan header `Authorization: Bearer <access_token>` pada semua endpoint yang dilindungi.
- Untuk introspection, gunakan `x-api-key: SmartCity@Introspect#2026`.

---
File ini direvisi untuk menambahkan pengujian endpoint lengkap dan membedakan admin/citizen secara jelas.
