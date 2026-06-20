/**
 * healthCheck.js
 * Cek status semua upstream service secara paralel.
 */

const axios = require('axios');

async function checkEndpoint(name, url) {
  try {
    const response = await axios.get(url, { timeout: 3000 });
    return {
      name,
      status: response.status === 200 ? 'healthy' : 'unhealthy',
      code: response.status,
      details: response.data || null,
    };
  } catch (error) {
    return {
      name,
      status: 'unhealthy',
      code: error.response ? error.response.status : 503,
      details: error.message,
    };
  }
}

async function aggregateHealth(services) {
  const checks = await Promise.all(
    services.map((service) => checkEndpoint(service.name, service.url))
  );

  return {
    status: checks.every((item) => item.status === 'healthy') ? 'healthy' : 'degraded',
    services: checks,
  };
}

module.exports = { aggregateHealth };