/**
 * roleCheck.js
 * Middleware untuk memverifikasi role dari JWT payload (req.user).
 * Harus dipasang SETELAH jwtMiddleware.
 */

function requireRole(...allowedRoles) {
  return (req, res, next) => {
    const role = req.user?.role;

    if (!role) {
      return res.status(401).json({
        status: 'error',
        code: 401,
        message: 'Authentication token is required',
      });
    }

    if (!allowedRoles.includes(role)) {
      return res.status(403).json({
        status: 'error',
        code: 403,
        message: `Forbidden: requires role ${allowedRoles.join(' or ')}`,
      });
    }

    return next();
  };
}

/**
 * requireServiceToken — khusus untuk endpoint IoT dan internal.
 * Memeriksa req.oauth (di-set oleh oauthIntrospectionMiddleware),
 * dan memastikan scope-nya 'service'.
 */
function requireServiceToken(req, res, next) {
  const oauth = req.oauth;

  if (!oauth || !oauth.active) {
    return res.status(401).json({
      status: 'error',
      code: 401,
      message: 'Valid service token is required',
    });
  }

  if (oauth.scope !== 'service') {
    return res.status(403).json({
      status: 'error',
      code: 403,
      message: 'Forbidden: service-level token required',
    });
  }

  return next();
}

module.exports = { requireRole, requireServiceToken };