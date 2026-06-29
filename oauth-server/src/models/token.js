/**
 * JWT Token Management
 * 
 * Semua token divalidasi menggunakan JWT signature verification.
 * Revoked tokens disimpan dalam memory map untuk instant revocation.
 */

const jwt = require('jsonwebtoken');

const jwtSecret = process.env.JWT_SECRET;
const accessTokenExpiry = parseInt(process.env.JWT_EXPIRES_IN || '3600', 10); // 1 jam
const refreshTokenExpiry = parseInt(process.env.REFRESH_TOKEN_EXPIRES_IN || '86400', 10); // 24 jam

if (!jwtSecret) {
  throw new Error('JWT_SECRET environment variable is required');
}

const revokedTokens = new Map();

/**
 * Create access token (short-lived)
 */
function createAccessToken(payload) {
  return jwt.sign(
    payload,
    jwtSecret,
    { expiresIn: accessTokenExpiry }
  );
}

/**
 * Create refresh token (long-lived)
 */
function createRefreshToken(payload) {
  return jwt.sign(
    payload,
    jwtSecret,
    { expiresIn: refreshTokenExpiry }
  );
}

/**
 * Create token pair (access + refresh)
 */
function createTokenPair(user) {
  const payload = {
    user_id: user.id,
    username: user.username,
    email: user.email,
    role: user.role,
    scope: user.role === 'service' ? 'service' : 'read write',
  };

  return {
    access_token: createAccessToken(payload),
    refresh_token: createRefreshToken(payload),
    token_type: 'Bearer',
    expires_in: accessTokenExpiry,
    scope: payload.scope,
  };
}

/**
 * Create a dedicated service token for IoT/internal callers
 */
function createServiceToken(serviceName = 'iot-service') {
  const payload = {
    user_id: 999,
    username: serviceName,
    email: `${serviceName}@smartcity.local`,
    role: 'service',
    scope: 'service',
  };

  return {
    access_token: createAccessToken(payload),
    token_type: 'Bearer',
    expires_in: accessTokenExpiry,
    scope: payload.scope,
  };
}

/**
 * Verify and decode JWT
 */
function verifyToken(token) {
  try {
    // Check if token is revoked
    if (revokedTokens.has(token)) {
      return null;
    }

    const decoded = jwt.verify(token, jwtSecret);
    return decoded;
  } catch (error) {
    return null;
  }
}

/**
 * Revoke a token (add to blacklist)
 */
function revokeToken(token) {
  try {
    const decoded = jwt.verify(token, jwtSecret, { ignoreExpiration: true });
    revokedTokens.set(token, {
      token,
      revoked_at: Date.now(),
      user_id: decoded.user_id,
    });
    return true;
  } catch (error) {
    return false;
  }
}

/**
 * Check if token is revoked
 */
function isTokenRevoked(token) {
  return revokedTokens.has(token);
}

/**
 * Introspect token (untuk gateway atau service lain)
 * Returns: { active, user_id, username, role, exp, ... }
 */
function introspectToken(token) {
  const decoded = verifyToken(token);
  if (!decoded) {
    return { active: false };
  }

  return {
    active: true,
    user_id: decoded.user_id,
    username: decoded.username,
    email: decoded.email,
    role: decoded.role,
    scope: decoded.scope || (decoded.role === 'service' ? 'service' : 'read write'),
    exp: decoded.exp,
  };
}

module.exports = {
  createAccessToken,
  createRefreshToken,
  createTokenPair,
  createServiceToken,
  verifyToken,
  revokeToken,
  isTokenRevoked,
  introspectToken,
};