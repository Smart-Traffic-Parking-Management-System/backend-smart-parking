/**
 * jwt.js
 * Middleware verifikasi JWT untuk protected routes.
 * Set req.user = decoded JWT payload setelah verifikasi berhasil.
 */

const jwt = require('jsonwebtoken');

function getBearerToken(req) {
  const authHeader = req.headers.authorization || '';
  const match = authHeader.match(/^Bearer\s+(.+)$/i);
  if (!match) return null;
  return match[1];
}

function jwtMiddleware(req, res, next) {
  const token = getBearerToken(req);

  if (!token) {
    return res.status(401).json({
      status: 'error',
      code: 401,
      message: 'Authentication token is required',
    });
  }

  const secret = process.env.JWT_SECRET;
  if (!secret) {
    return res.status(500).json({
      status: 'error',
      code: 500,
      message: 'JWT secret is not configured',
    });
  }

  try {
    const payload = jwt.verify(token, secret);
    req.user = payload; // { sub, username, role, citizen_id, ... }
    return next();
  } catch (error) {
    return res.status(401).json({
      status: 'error',
      code: 401,
      message: 'Invalid or expired token',
    });
  }
}

module.exports = { jwtMiddleware, getBearerToken };