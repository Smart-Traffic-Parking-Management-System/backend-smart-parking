const express = require('express');
const bcrypt = require('bcryptjs');
const { OAuth2Client } = require('google-auth-library');

const {
  createAccessToken,
  createRefreshToken,
  createTokenPair,
  createServiceToken,
  verifyToken,
  revokeToken,
  isTokenRevoked,
  introspectToken,
} = require('../models/token');

const {
  getUserByUsername,
  getUserByEmail,
  createUser,
  listAllUsers,
} = require('../models/user');

const router = express.Router();


// Helper: Format response JSON 
function formatResponse(status, code, data, message) {
  return {
    status,
    code,
    data,
    message,
    timestamp: new Date().toISOString(),
    service: 'oauth-server',
  };
}


router.post('/register', (req, res) => {
  try {
    const { username, email, password } = req.body;

    // Validasi input
    if (!username || !email || !password) {
      return res.status(400).json(
        formatResponse('error', 400, null, 'username, email, dan password wajib diisi')
      );
    }

    // Validasi format email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      return res.status(400).json(
        formatResponse('error', 400, null, 'Email format tidak valid')
      );
    }

    // Validasi password length
    if (password.length < 6) {
      return res.status(400).json(
        formatResponse('error', 400, null, 'Password minimal 6 karakter')
      );
    }

    // Cek duplikat username / email
    if (getUserByUsername(username) || getUserByEmail(email)) {
      return res.status(409).json(
        formatResponse('error', 409, null, 'Username atau email sudah terdaftar')
      );
    }

    // Hash password
    const passwordHash = bcrypt.hashSync(password, 10);

    // Create user
    const newUser = createUser(username, email, passwordHash, 'citizen');

    if (!newUser) {
      return res.status(400).json(
        formatResponse('error', 400, null, 'Gagal membuat user')
      );
    }

    return res.status(201).json(
      formatResponse('success', 201, newUser, 'User berhasil didaftarkan')
    );
  } catch (error) {
    console.error('Register error:', error);
    return res.status(500).json(
      formatResponse('error', 500, null, 'Server error')
    );
  }
});

router.post('/login', (req, res) => {
  try {
    const { username, password } = req.body;

    if (!username || !password) {
      return res.status(400).json(
        formatResponse('error', 400, null, 'username dan password wajib diisi')
      );
    }

    // Cari user
    const user = getUserByUsername(username);
    if (!user || !bcrypt.compareSync(password, user.password_hash)) {
      return res.status(401).json(
        formatResponse('error', 401, null, 'Username atau password salah')
      );
    }

    // Create token pair
    const tokens = createTokenPair(user);

    return res.status(200).json(
      formatResponse('success', 200, tokens, 'Login berhasil')
    );
  } catch (error) {
    console.error('Login error:', error);
    return res.status(500).json(
      formatResponse('error', 500, null, 'Server error')
    );
  }
});

router.post('/service-token', (req, res) => {
  try {
    const { service_name } = req.body;
    const tokenName = service_name || 'iot-service';

    const serviceToken = createServiceToken(tokenName);

    return res.status(200).json(
      formatResponse('success', 200, serviceToken, 'Service token berhasil dibuat')
    );
  } catch (error) {
    console.error('Service token error:', error);
    return res.status(500).json(
      formatResponse('error', 500, null, 'Server error')
    );
  }
});

router.post('/refresh', (req, res) => {
  try {
    const { refresh_token } = req.body;

    if (!refresh_token) {
      return res.status(400).json(
        formatResponse('error', 400, null, 'refresh_token wajib diisi')
      );
    }

    // Verify refresh token
    const decoded = verifyToken(refresh_token);
    if (!decoded) {
      return res.status(401).json(
        formatResponse('error', 401, null, 'Refresh token invalid atau expired')
      );
    }

    // Create new access token dengan payload yang sama
    const newAccessToken = createAccessToken({
      user_id: decoded.user_id,
      username: decoded.username,
      email: decoded.email,
      role: decoded.role,
    });

    return res.status(200).json(
      formatResponse('success', 200, {
        access_token: newAccessToken,
        token_type: 'Bearer',
        expires_in: parseInt(process.env.JWT_EXPIRES_IN || '3600', 10),
      }, 'Token berhasil direfresh')
    );
  } catch (error) {
    console.error('Refresh error:', error);
    return res.status(500).json(
      formatResponse('error', 500, null, 'Server error')
    );
  }
});

router.post('/revoke', (req, res) => {
  try {
    const { token } = req.body;

    if (!token) {
      return res.status(400).json(
        formatResponse('error', 400, null, 'token wajib diisi')
      );
    }

    // Revoke token
    const success = revokeToken(token);
    if (!success) {
      return res.status(400).json(
        formatResponse('error', 400, null, 'Token invalid atau sudah di-revoke')
      );
    }

    return res.status(200).json(
      formatResponse('success', 200, null, 'Token berhasil di-revoke')
    );
  } catch (error) {
    console.error('Revoke error:', error);
    return res.status(500).json(
      formatResponse('error', 500, null, 'Server error')
    );
  }
});

router.post('/introspect', (req, res) => {
  try {
    const { token } = req.body;

    if (!token) {
      return res.status(400).json(
        formatResponse('error', 400, null, 'token wajib diisi')
      );
    }

    // Introspect token
    const tokenInfo = introspectToken(token);

    return res.status(200).json(
      formatResponse('success', 200, tokenInfo, 'Token introspection successful')
    );
  } catch (error) {
    console.error('Introspect error:', error);
    return res.status(500).json(
      formatResponse('error', 500, null, 'Server error')
    );
  }
});



const googleClientId = process.env.GOOGLE_CLIENT_ID;
const googleClientSecret = process.env.GOOGLE_CLIENT_SECRET;
const googleCallbackUrl = process.env.GOOGLE_CALLBACK_URL;

let googleOauthClient = null;
if (googleClientId && googleClientSecret && googleCallbackUrl) {
  googleOauthClient = new OAuth2Client(googleClientId, googleClientSecret, googleCallbackUrl);

  /**
   * GET /google
   * Redirect ke Google consent page
   */
  router.get('/google', (req, res) => {
    try {
      const url = googleOauthClient.generateAuthUrl({
        access_type: 'offline',
        scope: ['openid', 'email', 'profile'],
        prompt: 'consent',
      });
      return res.redirect(url);
    } catch (error) {
      console.error('Google OAuth initiation error:', error);
      return res.status(500).json(
        formatResponse('error', 500, null, 'Google OAuth error')
      );
    }
  });

  /**
   * GET /google/callback
   * Google callback dengan authorization code
   */
  router.get('/google/callback', async (req, res) => {
    try {
      const code = req.query.code;
      if (!code) {
        return res.status(400).json(
          formatResponse('error', 400, null, 'Authorization code missing')
        );
      }

      // Get tokens from Google
      const { tokens } = await googleOauthClient.getToken(code);
      if (!tokens || !tokens.id_token) {
        return res.status(400).json(
          formatResponse('error', 400, null, 'No id_token from Google')
        );
      }

      // Verify ID token
      const ticket = await googleOauthClient.verifyIdToken({
        idToken: tokens.id_token,
        audience: googleClientId,
      });

      const payload = ticket.getPayload();
      const email = payload.email;
      const name = payload.name || email;

      // Cek apakah user sudah ada
      let user = getUserByEmail(email);

      // Jika belum ada, buat user baru dengan email sebagai username
      if (!user) {
        const username = email.split('@')[0]; // gunakan bagian sebelum @ sebagai username
        const passwordHash = bcrypt.hashSync(Math.random().toString(), 10); // random password
        user = createUser(username, email, passwordHash, 'citizen');
      }

      // Create token pair
      const tokenPair = createTokenPair(user);

      // Return as JSON
      return res.json(
        formatResponse('success', 200, {
          user: {
            id: user.id,
            username: user.username,
            email: user.email,
            role: user.role,
          },
          ...tokenPair,
        }, `Google login berhasil. Hai ${name}!`)
      );
    } catch (error) {
      console.error('Google callback error:', error);
      return res.status(500).json(
        formatResponse('error', 500, null, 'Google OAuth error')
      );
    }
  });
}

if (process.env.NODE_ENV === 'development') {
  router.get('/debug/users', (req, res) => {
    return res.json(
      formatResponse('success', 200, listAllUsers(), 'All users (admin only)')
    );
  });

  router.get('/debug/ping', (req, res) => {
    return res.json(
      formatResponse('success', 200, { env: process.env.NODE_ENV }, 'OAuth server is running')
    );
  });
}

module.exports = router;