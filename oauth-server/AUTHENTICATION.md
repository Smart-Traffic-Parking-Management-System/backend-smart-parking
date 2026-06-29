# OAuth Server - Authentication Documentation

## Perubahan Struktur (dari OAuth 2.0 complexity → JWT Simple)

### Yang Berubah:
1. **User Management**: Fleksibel - 1 admin + unlimited citizens
2. **Auth Type**: JWT (bukan OAuth2 grant type)
3. **Endpoints**: Simplified (Register, Login, Refresh, Revoke, Introspect)
4. **Google OAuth**: Tetap available sebagai opsi kedua

---

## Endpoints

### 1. POST `/register`
Register citizen user baru (siapa saja bisa register)

**Request:**
```json
{
  "username": "budi_santoso",
  "email": "budi@example.com",
  "password": "securepass123"
}
```

**Response (201):**
```json
{
  "status": "success",
  "code": 201,
  "data": {
    "id": 2,
    "username": "budi_santoso",
    "email": "budi@example.com",
    "role": "citizen",
    "created_at": "2026-01-15T10:30:00.000Z"
  },
  "message": "User berhasil didaftarkan",
  "timestamp": "2026-01-15T10:30:00.000Z",
  "service": "oauth-server"
}
```

**Validasi:**
- Username & email harus unik
- Email format wajib valid
- Password minimal 6 karakter

---

### 2. POST `/login`
Login user (admin atau citizen) dengan username + password

**Request:**
```json
{
  "username": "admin",
  "password": "admin@123"
}
```

**Response (200):**
```json
{
  "status": "success",
  "code": 200,
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "Bearer",
    "expires_in": 3600
  },
  "message": "Login berhasil",
  "timestamp": "2026-01-15T10:30:00.000Z",
  "service": "oauth-server"
}
```

**Token Payload:**
```json
{
  "user_id": 1,
  "username": "admin",
  "email": "admin@smartcity.local",
  "role": "admin",
  "iat": 1642244400,
  "exp": 1642248000
}
```

---

### 3. POST `/refresh`
Refresh access token menggunakan refresh token (untuk extend sesi)

**Request:**
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Response (200):**
```json
{
  "status": "success",
  "code": 200,
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "Bearer",
    "expires_in": 3600
  },
  "message": "Token berhasil direfresh",
  "timestamp": "2026-01-15T10:30:00.000Z",
  "service": "oauth-server"
}
```

---

### 4. POST `/revoke`
Revoke token (add ke blacklist - logout)

**Request:**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Response (200):**
```json
{
  "status": "success",
  "code": 200,
  "data": null,
  "message": "Token berhasil di-revoke",
  "timestamp": "2026-01-15T10:30:00.000Z",
  "service": "oauth-server"
}
```

---

### 5. POST `/introspect`
Verify token (untuk Gateway & service lain validasi token)

**Request:**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Response (200) - Token Valid:**
```json
{
  "status": "success",
  "code": 200,
  "data": {
    "active": true,
    "user_id": 1,
    "username": "admin",
    "email": "admin@smartcity.local",
    "role": "admin",
    "exp": 1642248000
  },
  "message": "Token introspection successful",
  "timestamp": "2026-01-15T10:30:00.000Z",
  "service": "oauth-server"
}
```

**Response (200) - Token Invalid:**
```json
{
  "status": "success",
  "code": 200,
  "data": {
    "active": false
  },
  "message": "Token introspection successful",
  "timestamp": "2026-01-15T10:30:00.000Z",
  "service": "oauth-server"
}
```

---

### 6. GET `/google`
Redirect ke Google consent page (untuk Google login)

**Usage:**
```
GET http://localhost:3002/oauth/google
```

---

### 7. GET `/google/callback`
Google OAuth callback (jangan call manual, Google yang panggil)

**Auto Flow:**
1. User klik "Login with Google"
2. Redirect ke `/google`
3. User authorize
4. Google redirect ke `/google/callback?code=...`
5. Server create user jika belum ada
6. Return token pair

---

## Direkomendasikan: Integration Flow

### Flow 1: Citizen Register & Login
```
1. POST /register
   {username, email, password}
   ↓
2. POST /login
   {username, password}
   ↓
3. Get access_token + refresh_token
   ↓
4. Use access_token di header: Authorization: Bearer <token>
```

### Flow 2: Refresh Token
```
1. Access token expired (exp < now)
   ↓
2. POST /refresh
   {refresh_token}
   ↓
3. Get new access_token
   ↓
4. Continue with new token
```

### Flow 3: Logout
```
1. POST /revoke
   {token: access_token}
   ↓
2. Token added ke blacklist
   ↓
3. Token tidak bisa digunakan lagi
```

### Flow 4: Google Login
```
1. Redirect user ke GET /google
   ↓
2. User authorize at Google
   ↓
3. Google callback to /google/callback
   ↓
4. User auto-created jika belum ada
   ↓
5. Return {user, access_token, refresh_token}
```

---

## Environment Variables

```env
PORT=3002
NODE_ENV=development

# JWT
JWT_SECRET=<random_32_char_string>
JWT_EXPIRES_IN=3600              # access token ttl
REFRESH_TOKEN_EXPIRES_IN=86400   # refresh token ttl

# Admin User (required)
ADMIN_USERNAME=admin
ADMIN_EMAIL=admin@smartcity.local
ADMIN_PASSWORD=admin@123

# Google OAuth (optional)
GOOGLE_CLIENT_ID=<from_google_console>
GOOGLE_CLIENT_SECRET=<from_google_console>
GOOGLE_CALLBACK_URL=http://localhost:3002/oauth/google/callback
```

---

## Testing dengan Postman

### 1. Register Citizen
```
POST http://localhost:3002/oauth/register
Body: raw JSON
{
  "username": "warga1",
  "email": "warga1@example.com",
  "password": "warga@123"
}
```

### 2. Login
```
POST http://localhost:3002/oauth/login
Body: raw JSON
{
  "username": "admin",
  "password": "admin@123"
}
```

### 3. Gunakan Token di Protected Endpoint
```
GET http://localhost:3000/api/citizens
Headers:
  Authorization: Bearer <access_token_dari_login>
```

### 4. Refresh Token
```
POST http://localhost:3002/oauth/refresh
Body: raw JSON
{
  "refresh_token": "<refresh_token_dari_login>"
}
```

### 5. Revoke Token
```
POST http://localhost:3002/oauth/revoke
Body: raw JSON
{
  "token": "<access_token>"
}
```

### 6. Introspect Token
```
POST http://localhost:3002/oauth/introspect
Body: raw JSON
{
  "token": "<access_token>"
}
```

---

## Error Responses

| Status | Scenario | Response |
|--------|----------|----------|
| 400 | Missing required field | `{"status":"error","code":400,"message":"..."}`  |
| 400 | Email format invalid | `{"status":"error","code":400,"message":"Email format tidak valid"}` |
| 400 | Password too short | `{"status":"error","code":400,"message":"Password minimal 6 karakter"}` |
| 409 | Username/email sudah ada | `{"status":"error","code":409,"message":"Username atau email sudah terdaftar"}` |
| 401 | Invalid credentials | `{"status":"error","code":401,"message":"Username atau password salah"}` |
| 401 | Invalid/expired token | `{"status":"error","code":401,"message":"Refresh token invalid atau expired"}` |
| 500 | Server error | `{"status":"error","code":500,"message":"Server error"}` |

---

## User Roles & Access Control

### Admin Role
- Can do everything
- Default username: `admin`
- Use `/login` → `role: "admin"`

### Citizen Role
- Created via `/register`
- Can login normally
- Use `/login` → `role: "citizen"`
- Limited access ke resources milik sendiri (enforced di PHP service)

---

## Debug Endpoints (Development Only)

### List All Users
```
GET http://localhost:3002/oauth/debug/users
Response:
[
  {
    "id": 1,
    "username": "admin",
    "email": "admin@smartcity.local",
    "role": "admin",
    "created_at": "2026-01-15T10:30:00.000Z"
  },
  ...
]
```

### Ping
```
GET http://localhost:3002/oauth/debug/ping
Response:
{
  "status": "success",
  "code": 200,
  "data": {"env": "development"},
  "message": "OAuth server is running",
  ...
}
```

---

## Next Steps

1. **Update Gateway** (`express-gateway`):
   - Change `/oauth/*` routing
   - Update JWT middleware ke endpoint baru
   - Endpoint: `POST /oauth/introspect` untuk verify token

2. **Update PHP Services** (`php-citizen`, `php-traffic`, `php-parking`):
   - Update client code untuk login ke `/oauth/login`
   - Parse JWT dari Authorization header
   - Extract `user_id`, `role` dari JWT payload

3. **Update Frontend/Postman**:
   - Use new endpoints: `/register`, `/login`, `/refresh`, `/revoke`
   - Store token di localStorage/session
   - Include token di setiap request: `Authorization: Bearer <token>`

---

## Migration Notes

Dari **OAuth2 grant-type model** → **JWT simple model**:
- ✅ `/token` (password) → `/login`
- ✅ `/token` (refresh_token) → `/refresh`
- ✅ `/revoke` → `/revoke` (sama)
- ✅ `/introspect` → `/introspect` (sama)
- ✅ Removed: `/token` (client_credentials) → use service account with hardcoded key
- ✅ Added: `/register` untuk citizen self-service registration
- ✅ Google OAuth tetap ada sebagai external provider

