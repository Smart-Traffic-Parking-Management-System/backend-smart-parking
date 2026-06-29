# OAuth Server Refactor - Summary of Changes

**Date:** 2026-01-15  
**Status:** ✅ COMPLETED

---

## 🎯 Objectives Achieved

### 1. User Management Flexibility
- ✅ **1 Default Admin** → Username: `admin`, Password: `admin@123`
- ✅ **Unlimited Citizens** → Can register via `/register` endpoint
- ✅ **No Hardcoded Limits** → Fleksibel untuk development & production

### 2. JWT-Based Authentication
- ✅ **Simplified OAuth** → Removed complex grant_type flows
- ✅ **Pure JWT** → Standard Bearer token authentication
- ✅ **Token Pair** → access_token (short-lived) + refresh_token (long-lived)

### 3. Endpoint Restructuring
Old (Complex OAuth2):
```
POST /token (grant_type=password)
POST /token (grant_type=refresh_token)
POST /token (grant_type=client_credentials)
POST /revoke
POST /introspect
```

New (Simple JWT):
```
POST /register          → Create citizen account
POST /login             → Get tokens (admin/citizen)
POST /refresh           → Extend session
POST /revoke            → Invalidate token
POST /introspect        → Verify token (Gateway use)
GET  /google            → Google OAuth (optional)
GET  /google/callback   → Google callback
```

### 4. Google OAuth Integration
- ✅ **Still Available** → As secondary auth method
- ✅ **Auto-Create User** → If email not found in system
- ✅ **Token Pair Response** → Same as local login

---

## 📁 Files Modified/Created

### New Files:
1. **`oauth-server/src/models/user.js`**
   - User management functions
   - getUserByUsername, getUserByEmail, createUser
   - initializeAdminUser on module load

2. **`oauth-server/AUTHENTICATION.md`**
   - Complete API documentation
   - Endpoint examples & flows
   - Testing guide (Postman)

### Modified Files:
1. **`oauth-server/src/models/token.js`**
   - Replaced with JWT-focused implementation
   - createAccessToken, createRefreshToken, createTokenPair
   - verifyToken, revokeToken, introspectToken

2. **`oauth-server/src/routes/oauth.js`**
   - Complete refactor with new endpoints
   - Simplified logic
   - Consistent response format

3. **`oauth-server/.env.example`**
   - Simplified config variables
   - Removed OAuth client credentials
   - Added admin defaults

4. **`oauth-server/.env`**
   - Updated with new structure
   - Kept existing JWT_SECRET (for compatibility)
   - Added ADMIN_* variables

---

## 🔑 Key Implementation Details

### Token Payload Structure
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

### Response Format (Consistent with plan.md)
```json
{
  "status": "success|error",
  "code": 200,
  "data": {},
  "message": "Human-readable message",
  "timestamp": "2026-01-15T10:30:00.000Z",
  "service": "oauth-server"
}
```

### User Model (In-Memory)
```javascript
{
  id: 1,
  username: "admin",
  email: "admin@smartcity.local",
  password_hash: "bcrypt_hash",
  role: "admin|citizen",
  created_at: "2026-01-15T10:30:00.000Z",
  is_active: true
}
```

---

## ✅ Testing Checklist

### Prerequisites
- [ ] OAuth server container running: `docker compose up -d oauth-server`
- [ ] Postman/Insomnia open with collection
- [ ] Check `.env` variables are set

### Test Scenarios
- [ ] **Register Citizen**
  ```bash
  POST http://localhost:3002/oauth/register
  {username, email, password}
  Expected: 201 Created
  ```

- [ ] **Login Admin**
  ```bash
  POST http://localhost:3002/oauth/login
  {username: "admin", password: "admin@123"}
  Expected: 200 OK, get access_token
  ```

- [ ] **Login Citizen**
  ```bash
  POST http://localhost:3002/oauth/login
  {username: <registered_username>, password}
  Expected: 200 OK, get access_token
  ```

- [ ] **Refresh Token**
  ```bash
  POST http://localhost:3002/oauth/refresh
  {refresh_token}
  Expected: 200 OK, new access_token
  ```

- [ ] **Introspect Token**
  ```bash
  POST http://localhost:3002/oauth/introspect
  {token: <access_token>}
  Expected: 200 OK, active: true
  ```

- [ ] **Revoke Token**
  ```bash
  POST http://localhost:3002/oauth/revoke
  {token: <access_token>}
  Expected: 200 OK
  ```

- [ ] **Introspect Revoked Token**
  ```bash
  POST http://localhost:3002/oauth/introspect
  {token: <revoked_token>}
  Expected: 200 OK, active: false
  ```

- [ ] **Debug Users** (dev only)
  ```bash
  GET http://localhost:3002/oauth/debug/users
  Expected: 200 OK, array of users
  ```

---

## 🔄 Integration Points (Next Steps)

### 1. Gateway Updates Required
**File:** `express-gateway/src/middleware/jwt.js`

Need to update JWT verification:
```javascript
// OLD
const payload = jwt.verify(token, jwtSecret);
const introspection = await axios.post(
  `${oauthServerUrl}/oauth/introspect`,
  { token, api_key: apiKey }
);

// NEW (simpler)
const decoded = jwt.verify(token, jwtSecret);
// Optionally still call introspect for revocation check
const introspection = await axios.post(
  `${oauthServerUrl}/oauth/introspect`,
  { token }
);
```

### 2. PHP Services Updates Required
**Files:** `php-citizen/public/index.php`, `php-traffic/public/index.php`, `php-parking/public/index.php`

Update JWT extraction:
```php
// OLD (handled by gateway only)
// Get token from Authorization header

// NEW (no change needed, still same header format)
$headers = getallheaders();
$auth = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $auth);

// Decode JWT to get user_id, role
$decoded = json_decode(base64_decode(explode('.', $token)[1]), true);
$user_id = $decoded['user_id'];
$role = $decoded['role'];
```

### 3. Client/Frontend Updates Required
- Update login form to POST `/login` instead of `/token`
- Add register form for `/register`
- Add refresh token logic (optional)
- Update token storage (localStorage/sessionStorage)

---

## 📊 Before & After Comparison

| Aspect | Before | After |
|--------|--------|-------|
| User Registration | Hardcoded in .env | Flexible via `/register` |
| User Count | 2 (admin + warga1) | Unlimited |
| Token Type | OAuth2 (complex) | JWT (simple) |
| Access Token TTL | 3600s | 3600s (same) |
| Refresh Token TTL | Custom | 86400s (1 day) |
| Grant Types | 3 (password, client_credentials, refresh) | 0 (not needed) |
| Endpoints | 3 | 7 (more feature-rich) |
| Admin Hardcoded | username: "admin" (env) | username: "admin" (env) |
| Citizen Hardcoded | username: "warga1" (env) | None (self-service) |
| Google OAuth | Available | Available (unchanged) |
| Response Format | Inconsistent | Consistent (plan.md standard) |

---

## 🚀 Migration Path for Team

### Phase 1: OAuth Server (DONE)
- ✅ Refactor token.js
- ✅ Refactor oauth.js
- ✅ Create user.js
- ✅ Update .env/.env.example
- ✅ Document endpoints

### Phase 2: Gateway (TODO)
- [ ] Update JWT middleware
- [ ] Test introspect endpoint
- [ ] Handle new response format

### Phase 3: PHP Services (TODO)
- [ ] Update header parsing (if needed)
- [ ] Test with new tokens
- [ ] Ensure user_id extraction works

### Phase 4: Frontend/Postman (TODO)
- [ ] Update login flow
- [ ] Add register form
- [ ] Update Postman collection

### Phase 5: Production (TODO)
- [ ] Use database instead of in-memory Map
- [ ] Implement proper password hashing
- [ ] Add rate limiting on `/register`
- [ ] Add email verification

---

## ⚠️ Breaking Changes

1. **Endpoint Change**: `/token` → `/login` or `/refresh`
   - **Impact**: Any client using old endpoints will break
   - **Mitigation**: Update Gateway & Postman collection

2. **Response Format**: Added `timestamp` & `service` fields
   - **Impact**: Clients parsing only `data` field will work fine
   - **Mitigation**: Provide parsing guide to team

3. **Token Payload**: Simplified structure
   - **Impact**: Removed `scope` field from token (still in introspect response)
   - **Mitigation**: Use `/introspect` if scope needed

4. **Admin User**: No longer configurable username
   - **Impact**: Username always "admin"
   - **Mitigation**: Password configurable via env
   - **Note**: Still can change in `.env`

---

## 📝 Notes

- Token validation still uses JWT signature verification (no database call needed)
- Revoked tokens stored in-memory (lost on server restart) - OK for dev
- For production, use database to persist revoked tokens
- Google OAuth auto-creates citizen account (doesn't auto-upgrade to admin)
- All passwords use bcryptjs (10 rounds) for security

---

## 🆘 Troubleshooting

### Token Not Working After Refresh
- Check if refresh_token is still valid
- Ensure JWT_SECRET hasn't changed
- Verify token not revoked

### Cannot Login with New User
- Ensure email format is valid
- Check password is >= 6 characters
- Verify username/email not duplicate

### Google OAuth Redirects to Error
- Check GOOGLE_CLIENT_ID & GOOGLE_CLIENT_SECRET in .env
- Verify GOOGLE_CALLBACK_URL matches Google Console
- Check network connectivity to Google

### 401 Unauthorized on Protected Endpoints
- Verify token in Authorization header
- Check token not expired (use `/introspect`)
- Ensure correct Bearer format: `Authorization: Bearer <token>`

---

## 📞 Contact / Questions

For questions about:
- **User Model**: See `oauth-server/src/models/user.js`
- **Token Management**: See `oauth-server/src/models/token.js`
- **Endpoints**: See `oauth-server/AUTHENTICATION.md`
- **Integration**: See Gateway & PHP services sections above

