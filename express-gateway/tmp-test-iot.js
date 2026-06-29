const http = require('http');
const jwt = require('jsonwebtoken');
const secret = process.env.JWT_SECRET || 'f64b8849028ed4ebe0d1c57a81501493428f5a30c86ea66880334d002a0adaab3d0861da87af38196f181c6005a83cf9b6b533d9d6e76500ca8c100bcf51ff95';
const token = jwt.sign({ user_id: 1, username: 'admin', email: 'admin@smartcity.local', role: 'admin', scope: 'read write' }, secret, { expiresIn: 3600 });

function request(path, body) {
  const data = JSON.stringify(body);
  const req = http.request({
    hostname: '127.0.0.1',
    port: 3000,
    path,
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
      'Content-Length': Buffer.byteLength(data)
    }
  }, (res) => {
    let raw = '';
    res.on('data', (chunk) => raw += chunk);
    res.on('end', () => {
      console.log(path + ' -> ' + res.statusCode);
      console.log(raw);
    });
  });
  req.on('error', (err) => {
    console.error(path + ' error', err.message);
  });
  req.write(data);
  req.end();
}

request('/iot/traffic', { zone_id: 1, avg_speed_kmh: 35, vehicle_density: 0.3, recorded_at: '2026-06-29T10:00:00Z' });
setTimeout(() => request('/iot/parking', { slot_id: 1, status: 'occupied', timestamp: '2026-06-29T10:00:00Z' }), 200);
