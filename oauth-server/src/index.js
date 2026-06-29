require('dotenv').config();
const express    = require('express');
const bodyParser = require('body-parser');
const oauthRoutes = require('./routes/oauth');

const app  = express();
const port = parseInt(process.env.PORT || '3002', 10);

app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: false }));
app.use((req, res, next) => {
  res.setHeader('Content-Type', 'application/json');
  next();
});

app.use('/oauth', oauthRoutes);

// Handle malformed JSON body parse failures with a clean client error.
app.use((err, req, res, next) => {
  if (err && err.type === 'entity.parse.failed') {
    return res.status(400).json({
      status: 'error',
      code: 400,
      message: 'Invalid JSON body',
      details: err.message,
    });
  }
  return next(err);
});

app.get('/health', (req, res) => {
  res.json({
    status:  'success',
    service: 'oauth-server',
    uptime:  process.uptime(),
  });
});

app.use((req, res) => {
  res.status(404).json({ status: 'error', code: 404, message: 'Route not found' });
});

app.use((err, req, res, next) => {
  console.error(err);
  res.status(500).json({ status: 'error', code: 500, message: 'Internal server error' });
});

app.listen(port, () => {
  console.log(`OAuth Server listening on port ${port}`);
});