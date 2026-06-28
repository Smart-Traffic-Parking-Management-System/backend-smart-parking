# Authentication & Authorization (Admin / Citizens)

Dokumentasi ini menjelaskan alur authentication di proyek, format request yang didukung, serta matriks akses endpoint → role.

## Ringkasan singkat
- Gateway: `http://localhost:3000` — semua request publik ke `/oauth/*` diproxy ke OAuth server.
- OAuth server mengeluarkan dua jenis token:
  - User token (JWT) — untuk `password` grant; payload termasuk `role` (`citizen`|`admin`) dan `citizen_id`/`user_id`.
  - Service token — untuk `client_credentials` grant; scope `service` (digunakan untuk IoT/ML/internal).

## Token endpoints
- `POST /oauth/token` — mintak token.
  - Supported `grant_type`:
    - `password` — body form-urlencoded: `grant_type=password&username=<>&password=<>` → hasil: `access_token` (JWT), `refresh_token`.
    - `client_credentials` — body form-urlencoded or Basic Auth. Example body: `grant_type=client_credentials&client_id=...&client_secret=...` atau header `Authorization: Basic base64(clientId:clientSecret)` → hasil: `access_token` (service token, scope=service).
    - `refresh_token` — body: `grant_type=refresh_token&refresh_token=<token>` → hasil: new `access_token`.
  - Content-Type: `application/x-www-form-urlencoded` (disarankan). Gateway juga meneruskan JSON jika dikirim.

- `POST /oauth/introspect` — cek token (dipanggil oleh Gateway/servis). Body JSON: `{ "token": "<access_token>" }`.
  - Requires header `x-api-key: <introspection-key>` (gateway mengirimkan `OAUTH_INTROSPECTION_API_KEY`).

- `POST /oauth/revoke` — cabut token. Body JSON: `{ "token": "<access_token>" }`.

- Google OAuth
  - `GET /oauth/google` → redirect ke Google.
  - `GET /oauth/google/callback` → callback; OAuth server akan mengeluarkan token internal (jika Google dikonfig).

## JWT payload (user token)
- Contoh fields yang disertakan:
  - `sub`, `user_id`, `citizen_id` (INT)
  - `username`
  - `role` — `citizen` atau `admin`

Gateway memverifikasi JWT (`JWT_SECRET`) dan menyetel `req.user` untuk akses downstream.

## Headers & Content-Type
- Token requests: `Content-Type: application/x-www-form-urlencoded`.
- Introspect/Revoke: `Content-Type: application/json` dan `x-api-key` untuk introspect.
- Protected API calls: `Authorization: Bearer <access_token>` (JWT or service token).

## Contoh curl singkat
- Password grant (user/admin):
```bash
curl -X POST http://localhost:3000/oauth/token \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'grant_type=password&username=admin&password=admin123'
```

- Client credentials (service):
```bash
curl -X POST http://localhost:3000/oauth/token \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'grant_type=client_credentials&client_id=smartcity_client&client_secret=<secret>'
```

- Introspect (gateway/internal):
```bash
curl -X POST http://localhost:3000/oauth/introspect \
  -H 'Content-Type: application/json' \
  -H 'x-api-key: SmartCity@Introspect#2026' \
  -d '{"token":"<access_token>"}'
```

## Matriks akses endpoint → role
Berikut ringkasan endpoint utama dan role yang diizinkan, sesuai `express-gateway/src/index.js`.

- PUBLIC (tanpa auth)
  - `GET /health`
  - `GET /` (gateway info)
  - `POST /api/citizens` (register)

- SERVICE only (client_credentials token with `scope=service`)
  - `GET /metrics` (internal)
  - `POST /iot/traffic` (Node-RED → Traffic Service)
  - `POST /iot/parking` (Node-RED → Parking Service)

- CITIZEN + ADMIN (JWT with `role=citizen|admin`)
  - `GET /api/citizens/:id` (citizen: biasanya hanya milik sendiri — filter di service)
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

- ADMIN only (JWT `role=admin`)
  - `PATCH /api/reports/:id/status`
  - `PATCH /api/incidents/:id/resolve`
  - `GET /model/feature-importance`
  - `POST /predict/batch`

- ADMIN + SERVICE (either admin JWT OR service token via introspect)
  - `POST /detect/anomaly` (gateway first tries JWT; if not admin, then introspect for service token)
  - `GET /model/feature-importance` (also accessible to service tokens)
  - `POST /predict/batch`

## Detail implementasi penting
- Gateway (`express-gateway/src/index.js`):
  - JWT middleware: `express-gateway/src/middleware/jwt.js` — memverifikasi token dan memasang `req.user`.
  - Role checks: `express-gateway/src/middleware/roleCheck.js` — `requireRole(...)` dan `requireServiceToken`.
  - OAuth proxy: `/oauth` diproxy ke OAuth server; gateway menerima `application/x-www-form-urlencoded` dan JSON, lalu meneruskannya.

- OAuth server (`oauth-server/src/routes/oauth.js`):
  - `password` grant menggunakan in-memory `users` map (seeded via env defaults) — user dengan `role: admin` ada di seed.
  - Token store in-memory (`oauth-server/src/models/token.js`) — berguna untuk development/debug only.
  - Introspect menerima header `x-api-key` atau `api_key` di body; env yang didukung: `INTROSPECTION_API_KEY` atau `OAUTH_INTROSPECTION_API_KEY`.

## Tips debugging cepat
- Jika `grant_type is required` saat memposting lewat gateway:
  - Pastikan `Content-Type: application/x-www-form-urlencoded` atau kirim JSON (gateway mendukung keduanya).
  - Restart gateway & oauth-server setelah perubahan kode.
  - Gunakan `GET /oauth/debug/ping` dan `GET /oauth/debug/tokens` (hanya di `NODE_ENV=development`) untuk memastikan server menerima request.

---
File ini dihasilkan otomatis; jika mau saya bisa tambahkan contoh Postman Tests untuk menyimpan token secara otomatis.
