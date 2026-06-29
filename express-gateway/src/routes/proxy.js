const { createProxyMiddleware } = require('http-proxy-middleware');

const createServiceProxy = (target) =>
  createProxyMiddleware({
    target,
    changeOrigin: true,
    pathRewrite: (path) => path.replace(/^\/api\//, '/api/'),
    onProxyReq(proxyReq, req) {
      proxyReq.setHeader('x-forwarded-host', proxyReq.getHeader('host') || '');

      // Tulis ulang body karena express.json() sudah consume stream-nya
      if (req.body && Object.keys(req.body).length > 0) {
        const bodyData = JSON.stringify(req.body);
        proxyReq.setHeader('Content-Type', 'application/json');
        proxyReq.setHeader('Content-Length', Buffer.byteLength(bodyData));
        proxyReq.write(bodyData);
      }
    },
    onError(err, req, res) {
      const message = err.message || 'Gateway proxy error';
      res.status(502).json({ status: 'error', code: 502, message });
    },
  });

module.exports = {
  citizenProxy: createServiceProxy(process.env.CITIZEN_SERVICE_URL),
  trafficProxy: createServiceProxy(process.env.TRAFFIC_SERVICE_URL),
  parkingProxy: createServiceProxy(process.env.PARKING_SERVICE_URL),
  pythonProxy:  createServiceProxy(process.env.PYTHON_ML_URL),
};