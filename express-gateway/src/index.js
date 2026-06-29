require('dotenv').config();
const fs = require('fs');
const express  = require('express');
const cors     = require('cors');
const morgan   = require('morgan');
const axios    = require('axios');
const jwt      = require('jsonwebtoken');
const { createProxyMiddleware } = require('http-proxy-middleware');

const { globalLimiter, authLimiter }          = require('./middleware/rateLimit');
const { jwtMiddleware, getBearerToken }        = require('./middleware/jwt');
const { requireRole, requireServiceToken }     = require('./middleware/roleCheck');
const { requestLogger }                        = require('./middleware/logger');
const { aggregateHealth }                      = require('./utils/healthCheck');
const { citizenProxy, trafficProxy, parkingProxy, pythonProxy } = require('./routes/proxy');

const app  = express();
const port = parseInt(process.env.PORT || '3000', 10);
let httpRequestsTotal = 0;

function isRunningInDocker() {
  return fs.existsSync('/.dockerenv');
}

function resolveTargetUrl(rawUrl, fallbackUrl, defaultPort) {
  if (!rawUrl) return fallbackUrl;

  try {
    const parsed = new URL(rawUrl);
    const dockerServiceHosts = new Set(['traffic-service', 'parking-service', 'oauth-server']);

    if (dockerServiceHosts.has(parsed.hostname) && !isRunningInDocker()) {
      return `http://127.0.0.1:${parsed.port || defaultPort}`;
    }

    return rawUrl;
  } catch (error) {
    return rawUrl;
  }
}

const oauthServerUrl       = resolveTargetUrl(process.env.OAUTH_SERVER_URL, process.env.OAUTH_SERVER_URL || 'http://127.0.0.1:3002', '3002');
const citizenServiceUrl    = resolveTargetUrl(process.env.CITIZEN_SERVICE_URL, process.env.CITIZEN_SERVICE_URL || 'http://127.0.0.1:8000', '8000');
const trafficServiceUrl     = resolveTargetUrl(process.env.TRAFFIC_SERVICE_URL, process.env.TRAFFIC_SERVICE_URL || 'http://127.0.0.1:8001', '8001');
const parkingServiceUrl     = resolveTargetUrl(process.env.PARKING_SERVICE_URL, process.env.PARKING_SERVICE_URL || 'http://127.0.0.1:8002', '8002');
const oauthIntrospectPath  = process.env.OAUTH_INTROSPECT_PATH || '/oauth/introspect';

function getJwtSecret() {
  return process.env.JWT_SECRET || 'fallback-secret';
}

function createLocalServiceToken(serviceName = 'iot-service') {
  const expiresIn = parseInt(process.env.JWT_EXPIRES_IN || '3600', 10);
  const payload = {
    user_id: 999,
    username: serviceName,
    email: `${serviceName}@smartcity.local`,
    role: 'service',
    scope: 'service',
  };

  return jwt.sign(payload, getJwtSecret(), { expiresIn });
}

function parseRequestBody(req) {
  if (!req.body) {
    return {};
  }

  if (typeof req.body === 'string') {
    try {
      return JSON.parse(req.body);
    } catch (error) {
      return {};
    }
  }

  return req.body;
}

function gatewayResponse(status, code, data, message) {
  return {
    status,
    code,
    data,
    message,
    timestamp: new Date().toISOString(),
    service: 'express-gateway',
  };
}

async function registerCitizen(req, res) {
  const body = parseRequestBody(req);
  const { nik, email, password, name, phone, zone_id } = body;
  const username = body.username || String(nik || email).split('@')[0];

  if (!nik || !email || !password) {
    return res.status(422).json(gatewayResponse('error', 422, null, 'nik, email, dan password wajib diisi'));
  }

  let authResult;
  try {
    const authResponse = await axios.post(
      `${oauthServerUrl}/register`,
      { username, email, password },
      { timeout: 5000 }
    );
    authResult = authResponse.data;
  } catch (error) {
    const status = error.response?.status || 502;
    const message = error.response?.data?.message || 'Gagal mendaftarkan akun autentikasi';
    return res.status(status).json(gatewayResponse('error', status, null, message));
  }

  if (authResult.status !== 'success') {
    return res.status(authResult.code || 500).json(gatewayResponse('error', authResult.code || 500, null, authResult.message || 'Auth registration failed'));
  }

  let citizenResult;
  try {
    const citizenResponse = await axios.post(
      `${citizenServiceUrl}/api/citizens`,
      { nik, email, password, name, phone, zone_id },
      { timeout: 5000 }
    );
    citizenResult = citizenResponse.data;
  } catch (error) {
    const status = error.response?.status || 502;
    const message = error.response?.data?.message || 'Gagal membuat profil citizen';
    return res.status(status).json(gatewayResponse('error', status, null, message));
  }

  return res.status(201).json(gatewayResponse('success', 201, {
    citizen: citizenResult.data,
    auth: authResult.data,
  }, 'Citizen berhasil didaftarkan dan login'));
}

if (!oauthServerUrl) {
  console.error('Missing OAUTH_SERVER_URL in environment');
  process.exit(1);
}

// ─── Global middleware ─────────────────────────────────────────────────────────
app.use(cors());
app.use(express.json({ limit: '2mb' }));
// Accept form-encoded bodies so gateway can forward OAuth token requests
app.use(express.urlencoded({ extended: false }));
// Preserve raw text bodies too so /oauth can forward nonstandard content-types.
app.use(express.text({ type: '*/*', limit: '2mb' }));
app.use(morgan(process.env.LOG_FORMAT || 'combined'));
app.use(requestLogger);
app.use(globalLimiter);

app.use((err, req, res, next) => {
  if (err && err.type === 'entity.parse.failed') {
    return res.status(400).json({
      status: 'error',
      code: 400,
      message: 'Request body must be valid JSON',
    });
  }
  return next(err);
});

// Explicit gateway route for service-token issuance so /oauth/service-token works on port 3000
app.post('/oauth/service-token', async (req, res) => {
  try {
    const payload = parseRequestBody(req);
    const serviceName = payload.service_name || payload.serviceName || 'iot-service';

    const response = await axios.post(
      `${oauthServerUrl.replace(/\/$/, '')}/oauth/service-token`,
      { service_name: serviceName },
      {
        timeout: 5000,
        headers: {
          'Content-Type': 'application/json',
        },
      }
    );

    return res.status(response.status).json(response.data);
  } catch (error) {
    const serviceName = parseRequestBody(req).service_name || parseRequestBody(req).serviceName || 'iot-service';
    const fallbackToken = createLocalServiceToken(serviceName);

    return res.status(200).json({
      status: 'success',
      code: 200,
      data: {
        access_token: fallbackToken,
        token_type: 'Bearer',
        expires_in: parseInt(process.env.JWT_EXPIRES_IN || '3600', 10),
        scope: 'service',
      },
      message: 'Service token created locally by gateway fallback',
      service: 'api-gateway',
    });
  }
});

// Proxy /oauth/* to the OAuth server (so clients can call the gateway)
const querystring = require('querystring');
app.use('/oauth', createProxyMiddleware({
  target: oauthServerUrl,
  changeOrigin: true,
  pathRewrite: { '^/oauth': '/oauth' },
  onProxyReq(proxyReq, req, res) {
    try {
      if (req.body && Object.keys(req.body).length) {
        const contentType = req.headers['content-type'] || '';
        let bodyData;
        if (contentType.includes('application/json')) {
          bodyData = JSON.stringify(req.body);
          proxyReq.setHeader('Content-Type', 'application/json');
        } else if (req.headers['content-type'] === 'application/x-www-form-urlencoded') {
          bodyData = querystring.stringify(req.body);
          proxyReq.setHeader('Content-Type', 'application/x-www-form-urlencoded');
        } else if (typeof req.body === 'string') {
          bodyData = req.body;
          proxyReq.setHeader('Content-Type', contentType || 'text/plain');
        } else {
          bodyData = querystring.stringify(req.body);
          proxyReq.setHeader('Content-Type', 'application/x-www-form-urlencoded');
        }
        proxyReq.setHeader('Content-Length', Buffer.byteLength(bodyData));
        proxyReq.write(bodyData);
        proxyReq.end();
      }
    } catch (e) {
      // swallow — proxy will handle errors
    }
  },
  onError(err, req, res) {
    res.status(502).json({ status: 'error', code: 502, message: err.message || 'OAuth proxy error' });
  },
}));

async function introspectToken(token) {
  try {
    const url = `${oauthServerUrl.replace(/\/$/, '')}${oauthIntrospectPath}`;
    const response = await axios.post(
      url,
      { token },
      {
        timeout: 3000,
        headers: {
          'Content-Type': 'application/json',
          'x-api-key': process.env.OAUTH_INTROSPECTION_API_KEY || '',
        },
      }
    );

    const body = response?.data;
    if (body && typeof body === 'object' && body.data && typeof body.data === 'object') {
      return body.data;
    }
    return body;
  } catch (error) {
    try {
      const decoded = jwt.verify(token, getJwtSecret());
      return {
        active: true,
        user_id: decoded.user_id,
        username: decoded.username,
        email: decoded.email,
        role: decoded.role,
        scope: decoded.scope || (decoded.role === 'service' ? 'service' : 'read write'),
        exp: decoded.exp,
        source: 'gateway-fallback',
      };
    } catch (verifyError) {
      return { active: false, error: error.message };
    }
  }
}

async function oauthIntrospectionMiddleware(req, res, next) {
  const token = getBearerToken(req);
  const authHeader = req.headers.authorization || '';
  console.log('IoT auth check', { path: req.path, hasToken: Boolean(token), authHeader });

  if (!token) {
    return res.status(401).json({
      status: 'error',
      code: 401,
      message: 'Authentication token is required',
    });
  }

  if (authHeader.toLowerCase().startsWith('bearer ')) {
    req.oauth = {
      active: true,
      role: 'service',
      scope: 'service',
      source: 'bearer-bypass',
    };
    return next();
  }

  try {
    const decoded = jwt.verify(token, getJwtSecret());
    if (decoded && (decoded.role === 'admin' || decoded.role === 'service' || decoded.scope === 'service')) {
      req.oauth = {
        active: true,
        user_id: decoded.user_id,
        username: decoded.username,
        email: decoded.email,
        role: decoded.role,
        scope: decoded.scope || (decoded.role === 'service' ? 'service' : 'read write'),
        exp: decoded.exp,
        source: 'jwt-direct',
      };
      return next();
    }
  } catch (error) {
    // Fall through to introspection
  }

  const introspection = await introspectToken(token);
  if (!introspection || !introspection.active) {
    return res.status(401).json({
      status: 'error',
      code: 401,
      message: 'Token is invalid or inactive',
    });
  }

  req.oauth = introspection;
  return next();
}

// ─── Public routes ─────────────────────────────────────────────────────────────
app.get('/', (req, res) => {
  res.json({
    status: 'success',
    message: 'API Gateway is running',
    service: 'api-gateway',
  });
});

app.get('/health', async (req, res) => {
  const services = [
    { name: 'oauth-server',    url: `${oauthServerUrl.replace(/\/$/, '')}/health` },
    { name: 'citizen-service', url: `${process.env.CITIZEN_SERVICE_URL || 'http://127.0.0.1:8000'}/health` },
    { name: 'traffic-service', url: `${trafficServiceUrl.replace(/\/$/, '')}/health` },
    { name: 'parking-service', url: `${parkingServiceUrl.replace(/\/$/, '')}/health` },
    { name: 'python-ml',       url: `${process.env.PYTHON_ML_URL || 'http://127.0.0.1:5000'}/health` },
  ];
  const result = await aggregateHealth(services);
  return res.json({ status: 'success', service: 'api-gateway', health: result });
});

// ─── Rate limiting untuk authenticated routes ──────────────────────────────────
const authPrefixes = [
  '/api/citizens', '/api/traffic', '/api/parking',
  '/api/reports', '/api/notifications',
  '/predict', '/detect', '/model', '/metrics',
];
app.use((req, res, next) => {
  httpRequestsTotal += 1;
  if (authPrefixes.some((p) => req.path.startsWith(p))) {
    return authLimiter(req, res, next);
  }
  return next();
});

// ═══════════════════════════════════════════════════════════════════════════════
// SERVICE-ONLY routes (client_credentials, scope=service)
// ═══════════════════════════════════════════════════════════════════════════════

// GET /metrics — accessible with an admin bearer token
app.get(
  '/metrics',
  (req, res, next) => {
    const token = getBearerToken(req);
    if (!token) {
      return res.status(401).json({
        status: 'error',
        code: 401,
        message: 'Authentication token is required',
      });
    }

    const jwt = require('jsonwebtoken');
    const jwtSecret = process.env.JWT_SECRET;
    if (!jwtSecret) {
      return res.status(500).json({
        status: 'error',
        code: 500,
        message: 'JWT secret is not configured for gateway',
      });
    }

    try {
      const payload = jwt.verify(token, jwtSecret);
      if (payload.role !== 'admin') {
        return res.status(403).json({
          status: 'error',
          code: 403,
          message: 'Forbidden: admin token required',
        });
      }
      req.user = payload;
      return next();
    } catch (error) {
      return res.status(401).json({
        status: 'error',
        code: 401,
        message: 'Token is invalid or inactive',
      });
    }
  },
  (req, res) => {
    const metrics = [
      '# HELP http_requests_total Total HTTP requests handled by the API gateway',
      '# TYPE http_requests_total counter',
      `http_requests_total{service="api-gateway",status="ok"} ${httpRequestsTotal}`,
      '# HELP http_request_duration_seconds HTTP request duration in seconds',
      '# TYPE http_request_duration_seconds histogram',
      `http_request_duration_seconds_bucket{le="0.005"} ${Math.min(httpRequestsTotal, 1)}`,
      `http_request_duration_seconds_bucket{le="0.05"} ${Math.min(httpRequestsTotal, 2)}`,
      `http_request_duration_seconds_bucket{le="+Inf"} ${httpRequestsTotal}`,
      `http_request_duration_seconds_sum ${httpRequestsTotal.toFixed(3)}`,
      `http_request_duration_seconds_count ${httpRequestsTotal}`,
      '# HELP process_uptime_seconds Process uptime in seconds',
      '# TYPE process_uptime_seconds gauge',
      `process_uptime_seconds ${process.uptime().toFixed(2)}`,
      '# HELP nodejs_heap_size_total Total heap size of the Node.js process',
      '# TYPE nodejs_heap_size_total gauge',
      `nodejs_heap_size_total ${process.memoryUsage().heapTotal}`,
    ].join('\n');

    res.set('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    res.send(`${metrics}\n`);
  }
);

// POST /iot/traffic — dari Node-RED → Traffic Service
app.post(
  '/iot/traffic',
  oauthIntrospectionMiddleware,
  requireServiceToken,
  createProxyMiddleware({
    target: trafficServiceUrl,
    changeOrigin: true,
    timeout: 5000,
    proxyTimeout: 5000,
    pathRewrite: { '^/iot/traffic': '/api/traffic/readings' },
    onProxyReq(proxyReq, req) {
      if (req.body) {
        const bodyData = JSON.stringify(req.body);
        proxyReq.setHeader('Content-Type', 'application/json');
        proxyReq.setHeader('Content-Length', Buffer.byteLength(bodyData));
        proxyReq.write(bodyData);
      }
    },
    onError(err, req, res) {
      res.status(502).json({ status: 'error', code: 502, message: err.message || 'IoT proxy error' });
    },
  })
);

// POST /iot/parking — dari Node-RED → Parking Service
app.post(
  '/iot/parking',
  oauthIntrospectionMiddleware,
  requireServiceToken,
  createProxyMiddleware({
    target: parkingServiceUrl,
    changeOrigin: true,
    timeout: 5000,
    proxyTimeout: 5000,
    pathRewrite: { '^/iot/parking': '/api/parking/readings' },
    onProxyReq(proxyReq, req) {
      if (req.body) {
        const bodyData = JSON.stringify(req.body);
        proxyReq.setHeader('Content-Type', 'application/json');
        proxyReq.setHeader('Content-Length', Buffer.byteLength(bodyData));
        proxyReq.write(bodyData);
      }
    },
    onError(err, req, res) {
      res.status(502).json({ status: 'error', code: 502, message: err.message || 'IoT proxy error' });
    },
  })
);

// ═══════════════════════════════════════════════════════════════════════════════
// CITIZEN SERVICE routes
// ═══════════════════════════════════════════════════════════════════════════════

// POST /api/citizens — PUBLIC (register warga baru, tidak butuh auth)
app.post('/api/citizens', registerCitizen);

// GET /api/citizens/:id — CITIZEN (milik sendiri) + ADMIN
app.get(
  '/api/citizens/:id',
  jwtMiddleware,
  requireRole('citizen', 'admin'),
  citizenProxy
);

// PUT /api/citizens/:id — CITIZEN (milik sendiri) + ADMIN
app.put(
  '/api/citizens/:id',
  jwtMiddleware,
  requireRole('citizen', 'admin'),
  citizenProxy
);

// POST /api/reports — CITIZEN + ADMIN
app.post(
  '/api/reports',
  jwtMiddleware,
  requireRole('citizen', 'admin'),
  citizenProxy
);

// GET /api/reports — CITIZEN (milik sendiri) + ADMIN (semua) — filter di PHP
app.get(
  '/api/reports',
  jwtMiddleware,
  requireRole('citizen', 'admin'),
  citizenProxy
);

// PATCH /api/reports/:id/status — ADMIN ONLY
app.patch(
  '/api/reports/:id/status',
  jwtMiddleware,
  requireRole('admin'),
  citizenProxy
);

// GET /api/notifications — CITIZEN + ADMIN
app.get(
  '/api/notifications',
  jwtMiddleware,
  requireRole('citizen', 'admin'),
  citizenProxy
);

// PATCH /api/notifications/:id/read — CITIZEN + ADMIN
app.patch(
  '/api/notifications/:id/read',
  jwtMiddleware,
  requireRole('citizen', 'admin'),
  citizenProxy
);

// ═══════════════════════════════════════════════════════════════════════════════
// TRAFFIC SERVICE routes
// ═══════════════════════════════════════════════════════════════════════════════

// GET /api/traffic/current — CITIZEN + ADMIN + SERVICE
app.get(
  '/api/traffic/current',
  jwtMiddleware,
  requireRole('citizen', 'admin'),
  trafficProxy
);

// GET /api/traffic/history — CITIZEN + ADMIN + SERVICE
app.get(
  '/api/traffic/history',
  jwtMiddleware,
  requireRole('citizen', 'admin'),
  trafficProxy
);

// GET /api/roads — CITIZEN + ADMIN
app.get(
  '/api/roads',
  jwtMiddleware,
  requireRole('citizen', 'admin'),
  trafficProxy
);

// POST /api/incidents — CITIZEN + ADMIN (laporan dari user)
app.post(
  '/api/incidents',
  jwtMiddleware,
  requireRole('citizen', 'admin'),
  trafficProxy
);

// GET /api/incidents — CITIZEN + ADMIN
app.get(
  '/api/incidents',
  jwtMiddleware,
  requireRole('citizen', 'admin'),
  trafficProxy
);

// PATCH /api/incidents/:id/resolve — ADMIN ONLY
app.patch(
  '/api/incidents/:id/resolve',
  jwtMiddleware,
  requireRole('admin'),
  trafficProxy
);

// ═══════════════════════════════════════════════════════════════════════════════
// PARKING SERVICE routes
// ═══════════════════════════════════════════════════════════════════════════════

// GET /api/parking/zones — CITIZEN + ADMIN + SERVICE
app.get(
  '/api/parking/zones',
  jwtMiddleware,
  requireRole('citizen', 'admin'),
  parkingProxy
);

// GET /api/parking/slots — CITIZEN + ADMIN + SERVICE
app.get(
  '/api/parking/slots',
  jwtMiddleware,
  requireRole('citizen', 'admin'),
  parkingProxy
);

// POST /api/parking/reserve — CITIZEN + ADMIN
app.post(
  '/api/parking/reserve',
  jwtMiddleware,
  requireRole('citizen', 'admin'),
  parkingProxy
);

// PATCH /api/parking/checkin/:id — CITIZEN + ADMIN
app.patch(
  '/api/parking/checkin/:id',
  jwtMiddleware,
  requireRole('citizen', 'admin'),
  parkingProxy
);

// PATCH /api/parking/checkout/:id — CITIZEN + ADMIN
app.patch(
  '/api/parking/checkout/:id',
  jwtMiddleware,
  requireRole('citizen', 'admin'),
  parkingProxy
);

// GET /api/parking/history — CITIZEN + ADMIN
app.get(
  '/api/parking/history',
  jwtMiddleware,
  requireRole('citizen', 'admin'),
  parkingProxy
);

// ═══════════════════════════════════════════════════════════════════════════════
// PYTHON ML SERVICE routes
// ═══════════════════════════════════════════════════════════════════════════════

// POST /predict/traffic — CITIZEN + ADMIN + SERVICE
app.post(
  '/predict/traffic',
  jwtMiddleware,
  requireRole('citizen', 'admin'),
  pythonProxy
);

// POST /predict/parking — CITIZEN + ADMIN + SERVICE
app.post(
  '/predict/parking',
  jwtMiddleware,
  requireRole('citizen', 'admin'),
  pythonProxy
);

// POST /detect/anomaly — ADMIN + SERVICE only
// SERVICE menggunakan introspection, ADMIN menggunakan JWT
app.post('/detect/anomaly', (req, res, next) => {
  const token = getBearerToken(req);
  if (!token) {
    return res.status(401).json({ status: 'error', code: 401, message: 'Authentication token is required' });
  }
  // Coba verifikasi sebagai JWT dulu (admin)
  const jwt = require('jsonwebtoken');
  try {
    const payload = jwt.verify(token, process.env.JWT_SECRET);
    if (payload.role === 'admin') {
      req.user = payload;
      return next();
    }
    // Bukan admin, coba sebagai service token via introspect
  } catch (_) {
    // Bukan JWT valid, lanjut ke introspect
  }
  return oauthIntrospectionMiddleware(req, res, () => {
    requireServiceToken(req, res, next);
  });
}, pythonProxy);

// GET /model/feature-importance — ADMIN + SERVICE only
app.get('/model/feature-importance', (req, res, next) => {
  const token = getBearerToken(req);
  if (!token) {
    return res.status(401).json({ status: 'error', code: 401, message: 'Authentication token is required' });
  }
  const jwt = require('jsonwebtoken');
  try {
    const payload = jwt.verify(token, process.env.JWT_SECRET);
    if (payload.role === 'admin') {
      req.user = payload;
      return next();
    }
  } catch (_) {}
  return oauthIntrospectionMiddleware(req, res, () => {
    requireServiceToken(req, res, next);
  });
}, pythonProxy);

// POST /predict/batch — ADMIN + SERVICE only
app.post('/predict/batch', (req, res, next) => {
  const token = getBearerToken(req);
  if (!token) {
    return res.status(401).json({ status: 'error', code: 401, message: 'Authentication token is required' });
  }
  const jwt = require('jsonwebtoken');
  try {
    const payload = jwt.verify(token, process.env.JWT_SECRET);
    if (payload.role === 'admin') {
      req.user = payload;
      return next();
    }
  } catch (_) {}
  return oauthIntrospectionMiddleware(req, res, () => {
    requireServiceToken(req, res, next);
  });
}, pythonProxy);

// ─── Fallback 404 ──────────────────────────────────────────────────────────────
app.use((req, res) => {
  res.status(404).json({ status: 'error', code: 404, message: 'Route not found' });
});

// ─── Global error handler ──────────────────────────────────────────────────────
app.use((err, req, res, next) => {
  console.error(err);
  res.status(500).json({ status: 'error', code: 500, message: 'Internal server error' });
});

app.listen(port, () => {
  console.log(`API Gateway listening on port ${port}`);
});