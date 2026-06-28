/**
 * oauth-server/src/routes/oauth.js
 *
 * Penyesuaian terhadap schema.sql:
 *
 * 1. citizen_id adalah INT (sesuai citizens.id AUTO_INCREMENT di DB).
 *    Seed data: Budi=1, Siti=2, Dewi=3, Ahmad=4, Admin=5.
 *    User store in-memory mengikuti urutan tersebut.
 *
 * 2. Token payload menyertakan user_id (INT) — nama field yang sama dengan
 *    kolom oauth_tokens.user_id agar mudah dimigrasi ke DB query nantinya.
 *    citizen_id di payload adalah alias user_id untuk konsistensi dengan
 *    service PHP yang membaca 'citizen_id' dari JWT.
 *
 * 3. isActive() menggantikan pengecekan flag 'active'. Logika:
 *    expires_at > now AND revoked_at === null
 *    — identik dengan kondisi SQL pada tabel oauth_tokens.
 *
 * 4. scope disimpan di token store dan dikembalikan saat introspect,
 *    sesuai kolom oauth_tokens.scope.
 */

const express = require('express');
const bcrypt  = require('bcryptjs');
const jwt     = require('jsonwebtoken');
const { v4: uuidv4 } = require('uuid');
const { OAuth2Client } = require('google-auth-library');
const { saveToken, getToken, revokeToken, isActive, listTokens } = require('../models/token');

const router = express.Router();

const jwtSecret             = process.env.JWT_SECRET;
const jwtExpiresIn          = process.env.JWT_EXPIRES_IN || '3600';
const refreshTokenExpiresIn = parseInt(process.env.REFRESH_TOKEN_EXPIRES_IN || '86400', 10);

if (!jwtSecret) {
  throw new Error('JWT_SECRET is required');
}

const clientId     = process.env.OAUTH_CLIENT_ID;
const clientSecret = process.env.OAUTH_CLIENT_SECRET;

/**
 * User store in-memory.
 * citizen_id (= user_id di oauth_tokens) adalah INT sesuai citizens.id dari seed.sql:
 *   1 = Budi Santoso  (citizen)
 *   2 = Siti Rahayu   (citizen)
 *   3 = Dewi Putri    (citizen)
 *   4 = Ahmad Fauzi   (citizen)
 *   5 = Admin Kota    (admin)
 *
 * Untuk production: ganti Map ini dengan query:
 *   SELECT id, email, password_hash, role FROM citizens WHERE email = ?
 */
const users = new Map([
  [
    process.env.USER_USERNAME || 'admin',
    {
      username:     process.env.USER_USERNAME || 'admin',
      passwordHash: bcrypt.hashSync(process.env.USER_PASSWORD || 'admin123', 10),
      role:         'admin',
      user_id:      5,   // citizens.id = 5 (Admin Kota) sesuai seed.sql
    },
  ],
  [
    process.env.CITIZEN_USERNAME || 'warga1',
    {
      username:     process.env.CITIZEN_USERNAME || 'warga1',
      passwordHash: bcrypt.hashSync(process.env.CITIZEN_PASSWORD || 'warga123', 10),
      role:         'citizen',
      user_id:      1,   // citizens.id = 1 (Budi Santoso) sesuai seed.sql
    },
  ],
]);

function createAccessToken(payload) {
  return jwt.sign(payload, jwtSecret, { expiresIn: Number(jwtExpiresIn) });
}

// ─── POST /oauth/token ─────────────────────────────────────────────────────────
// Use global body parsers (JSON and urlencoded) configured in src/index.js
router.post('/token', (req, res) => {
  const grantType = req.body.grant_type;

  if (!grantType) {
    return res.status(400).json({ status: 'error', code: 400, message: 'grant_type is required' });
  }

  // ── grant: password ──────────────────────────────────────────────────────────
  if (grantType === 'password') {
    const { username, password } = req.body;

    if (!username || !password) {
      return res.status(400).json({
        status: 'error', code: 400,
        message: 'username and password are required',
      });
    }

    const user = users.get(username);
    if (!user || !bcrypt.compareSync(password, user.passwordHash)) {
      return res.status(401).json({
        status: 'error', code: 401,
        message: 'Invalid credentials',
      });
    }

    const expiresAt    = Date.now() + Number(jwtExpiresIn) * 1000;
    const accessToken  = createAccessToken({
      sub:        user.user_id,    // INT — sesuai oauth_tokens.user_id
      user_id:    user.user_id,    // INT — alias eksplisit untuk service PHP
      citizen_id: user.user_id,    // INT — dibaca oleh PHP untuk filter data
      username:   user.username,
      role:       user.role,       // 'citizen' | 'admin'
    });
    const refreshToken = uuidv4();

    const tokenData = {
      // Kolom oauth_tokens
      client_id:     clientId,
      user_id:       user.user_id,   // INT, FK ke citizens.id
      access_token:  accessToken,
      refresh_token: refreshToken,
      scope:         'read write',
      expires_at:    expiresAt,
      revoked_at:    null,
      // Tambahan untuk response JSON (tidak disimpan ke DB sebagai kolom)
      token_type:    'Bearer',
      expires_in:    Number(jwtExpiresIn),
      username:      user.username,
      role:          user.role,
    };

    saveToken(tokenData);
    return res.json({
      access_token:  accessToken,
      token_type:    'Bearer',
      expires_in:    Number(jwtExpiresIn),
      refresh_token: refreshToken,
      scope:         'read write',
    });
  }

  // ── grant: client_credentials ─────────────────────────────────────────────────
  // Untuk Node-RED (IoT), Python ML, komunikasi antar-service.
  // user_id = NULL karena tidak ada citizen yang login.
  if (grantType === 'client_credentials') {
    const auth = req.headers.authorization || '';
    const [providedClientId, providedClientSecret] = Buffer.from(
      auth.replace(/^Basic\s+/i, ''), 'base64'
    ).toString().split(':');

    const resolvedClientId     = req.body.client_id     || providedClientId;
    const resolvedClientSecret = req.body.client_secret || providedClientSecret;

    if (resolvedClientId !== clientId || resolvedClientSecret !== clientSecret) {
      return res.status(401).json({
        status: 'error', code: 401,
        message: 'Invalid client credentials',
      });
    }

    const expiresAt   = Date.now() + Number(jwtExpiresIn) * 1000;
    const accessToken = createAccessToken({
      client_id: clientId,
      scope:     'service',
    });

    const tokenData = {
      // Kolom oauth_tokens
      client_id:     clientId,
      user_id:       null,           // NULL — tidak ada citizen yang login
      access_token:  accessToken,
      refresh_token: null,
      scope:         'service',
      expires_at:    expiresAt,
      revoked_at:    null,
      // Tambahan
      token_type:    'Bearer',
      expires_in:    Number(jwtExpiresIn),
    };

    saveToken(tokenData);
    return res.json({
      access_token: accessToken,
      token_type:   'Bearer',
      expires_in:   Number(jwtExpiresIn),
      scope:        'service',
    });
  }

  // ── grant: refresh_token ──────────────────────────────────────────────────────
  if (grantType === 'refresh_token') {
    const refreshToken = req.body.refresh_token;
    if (!refreshToken) {
      return res.status(400).json({
        status: 'error', code: 400,
        message: 'refresh_token is required',
      });
    }

    const tokenRecord = getToken(refreshToken);

    // Cek: token ada, refresh_token cocok, belum dicabut, belum kadaluarsa
    if (!tokenRecord || tokenRecord.refresh_token !== refreshToken || !isActive(tokenRecord)) {
      return res.status(401).json({
        status: 'error', code: 401,
        message: 'Invalid, expired, or revoked refresh token',
      });
    }

    const expiresAt   = Date.now() + refreshTokenExpiresIn * 1000;
    const accessToken = createAccessToken({
      sub:        tokenRecord.user_id,
      user_id:    tokenRecord.user_id,
      citizen_id: tokenRecord.user_id,
      username:   tokenRecord.username,
      role:       tokenRecord.role,
    });

    const newTokenData = {
      client_id:     tokenRecord.client_id,
      user_id:       tokenRecord.user_id,
      access_token:  accessToken,
      refresh_token: tokenRecord.refresh_token, // refresh token tetap sama
      scope:         tokenRecord.scope,
      expires_at:    expiresAt,
      revoked_at:    null,
      token_type:    'Bearer',
      expires_in:    Number(jwtExpiresIn),
      username:      tokenRecord.username,
      role:          tokenRecord.role,
    };

    saveToken(newTokenData);
    return res.json({
      access_token:  accessToken,
      token_type:    'Bearer',
      expires_in:    Number(jwtExpiresIn),
      refresh_token: tokenRecord.refresh_token,
      scope:         tokenRecord.scope,
    });
  }

  return res.status(400).json({
    status: 'error', code: 400,
    message: 'Unsupported grant_type. Supported: password, client_credentials, refresh_token',
  });
});

// ─── POST /oauth/introspect ────────────────────────────────────────────────────
// Hanya dapat diakses oleh Gateway via x-api-key.
// Mengembalikan field yang dibutuhkan Gateway dan service PHP:
//   active, role, user_id/citizen_id, scope
router.post('/introspect', (req, res) => {
  const apiKey = req.headers['x-api-key'] || req.body.api_key;
  // Accept either INTROSPECTION_API_KEY (oauth-server) or
  // OAUTH_INTROSPECTION_API_KEY (gateway .env naming) for flexibility.
  const expectedApiKey = process.env.INTROSPECTION_API_KEY || process.env.OAUTH_INTROSPECTION_API_KEY;

  if (!expectedApiKey || apiKey !== expectedApiKey) {
    return res.status(403).json({
      status: 'error', code: 403,
      message: 'Invalid introspection API key',
    });
  }

  const token = req.body.token;
  if (!token) {
    return res.status(400).json({
      status: 'error', code: 400,
      message: 'token is required',
    });
  }

  try {
    const payload     = jwt.verify(token, jwtSecret);
    const tokenRecord = getToken(token);

    // Aktif jika JWT valid DAN token di store belum dicabut & belum kadaluarsa
    const active = Boolean(payload && tokenRecord && isActive(tokenRecord));

    return res.json({
      active,
      sub:        payload.sub        || null,
      user_id:    payload.user_id    || null,  // INT, FK citizens.id
      citizen_id: payload.citizen_id || null,  // alias user_id untuk service PHP
      username:   payload.username   || null,
      role:       payload.role       || null,  // 'citizen' | 'admin' | null (service)
      scope:      tokenRecord?.scope  || null, // 'read write' | 'service'
      client_id:  tokenRecord?.client_id || null,
      expires_at: tokenRecord?.expires_at || null,
    });
  } catch (error) {
    return res.json({ active: false });
  }
});

// ─── POST /oauth/revoke ────────────────────────────────────────────────────────
// Mengisi revoked_at di token store (ekuivalen dengan UPDATE oauth_tokens
// SET revoked_at = NOW() WHERE access_token = ? di DB production).
router.post('/revoke', (req, res) => {
  const token = req.body.token;
  if (!token) {
    return res.status(400).json({
      status: 'error', code: 400,
      message: 'token is required',
    });
  }

  const revoked = revokeToken(token);
  if (!revoked) {
    return res.status(404).json({
      status: 'error', code: 404,
      message: 'Token not found',
    });
  }

  return res.json({ status: 'success', message: 'Token revoked successfully' });
});

// ─── Google OAuth routes (external identity provider) ─────────────────────────
// Requires these env vars: GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_CALLBACK_URL
const googleClientId = process.env.GOOGLE_CLIENT_ID;
const googleClientSecret = process.env.GOOGLE_CLIENT_SECRET;
const googleCallbackUrl = process.env.GOOGLE_CALLBACK_URL;

let googleOauthClient = null;
if (googleClientId && googleClientSecret && googleCallbackUrl) {
  googleOauthClient = new OAuth2Client(googleClientId, googleClientSecret, googleCallbackUrl);

  // Redirect user to Google's consent page
  router.get('/google', (req, res) => {
    const url = googleOauthClient.generateAuthUrl({
      access_type: 'offline',
      scope: ['openid', 'email', 'profile'],
      prompt: 'consent',
    });
    return res.redirect(url);
  });

  // Callback that Google will call with authorization code
  router.get('/google/callback', async (req, res) => {
    const code = req.query.code;
    if (!code) {
      return res.status(400).json({ status: 'error', code: 400, message: 'code is required' });
    }

    try {
      const { tokens } = await googleOauthClient.getToken(code);
      if (!tokens || !tokens.id_token) {
        return res.status(400).json({ status: 'error', code: 400, message: 'No id_token from Google' });
      }

      // Verify ID token and extract user info
      const ticket = await googleOauthClient.verifyIdToken({ idToken: tokens.id_token, audience: googleClientId });
      const payload = ticket.getPayload();
      const name = payload.name || payload.email || 'User';
      const email = payload.email || null;

      // Issue internal JWT (same format as password grant)
      const accessToken = createAccessToken({
        email,
        name,
        role: 'citizen',
      });
      const refreshToken = uuidv4();
      const expiresAt = Date.now() + Number(jwtExpiresIn) * 1000;

      const tokenData = {
        client_id: clientId,
        user_id: null,
        access_token: accessToken,
        refresh_token: refreshToken,
        scope: 'read',
        expires_at: expiresAt,
        revoked_at: null,
        token_type: 'Bearer',
        expires_in: Number(jwtExpiresIn),
        username: email,
        role: 'citizen',
      };

      saveToken(tokenData);

      // Return greeting as requested
      res.setHeader('Content-Type', 'text/plain');
      return res.send(`Hai, ${name}`);
    } catch (error) {
      console.error('Google OAuth callback error:', error);
      return res.status(500).json({ status: 'error', code: 500, message: 'Google OAuth error' });
    }
  });
}

module.exports = router;

// --- Development-only debug routes to aid Postman/manual testing ---
if (process.env.NODE_ENV === 'development') {
  // List active token records (non-production helper)
  router.get('/debug/tokens', (req, res) => {
    return res.json({ status: 'success', tokens: listTokens() });
  });

  // Simple ping to verify router is loaded
  router.get('/debug/ping', (req, res) => res.json({ status: 'success', service: 'oauth-server', env: process.env.NODE_ENV }));
}