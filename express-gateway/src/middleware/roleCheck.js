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

function requireServiceToken(req, res, next) {
  const oauth = req.oauth;

  if (!oauth || !oauth.active) {
    return res.status(401).json({
      status: 'error',
      code: 401,
      message: 'Valid service token is required',
    });
  }

  const isServiceToken = oauth.scope === 'service' || oauth.scope === 'read write' || oauth.scope?.includes('service');
  const isAdminToken = oauth.role === 'admin';

  if (!isServiceToken && !isAdminToken) {
    return res.status(403).json({
      status: 'error',
      code: 403,
      message: 'Forbidden: service-level or admin token required',
    });
  }

  return next();
}

module.exports = { requireRole, requireServiceToken };