/**
 * rateLimit.js
 * Global limiter: 100 req / 15 menit per IP (semua traffic).
 * Auth limiter : 500 req / jam per token atau IP (protected routes).
 */

const rateLimit = require('express-rate-limit');

const globalLimiter = rateLimit({
  windowMs: parseInt(process.env.GLOBAL_RATE_LIMIT_WINDOW_MINUTES || '15', 10) * 60 * 1000,
  max: parseInt(process.env.GLOBAL_RATE_LIMIT_MAX || '100', 10),
  standardHeaders: true,
  legacyHeaders: false,
  message: { status: 'error', code: 429, message: 'Too many requests' },
});

const authLimiter = rateLimit({
  windowMs: parseInt(process.env.AUTH_RATE_LIMIT_WINDOW_MINUTES || '60', 10) * 60 * 1000,
  max: parseInt(process.env.AUTH_RATE_LIMIT_MAX || '500', 10),
  standardHeaders: true,
  legacyHeaders: false,
  keyGenerator: (req) => req.headers.authorization || req.ip,
  message: { status: 'error', code: 429, message: 'Too many requests' },
});

module.exports = { globalLimiter, authLimiter };