
function requestLogger(req, res, next) {
  const start = Date.now();

  res.on('finish', () => {
    const duration = Date.now() - start;
    const log = {
      timestamp: new Date().toISOString(),
      method: req.method,
      path: req.originalUrl,
      status: res.statusCode,
      duration_ms: duration,
      ip: req.ip,
      role: req.user?.role || req.oauth?.scope || 'unauthenticated',
    };
    // Tampilkan sebagai JSON line agar mudah di-parse oleh log aggregator
    console.log(JSON.stringify(log));
  });

  return next();
}

module.exports = { requestLogger };