const tokens = new Map();

function isActive(record) {
  if (!record) return false;
  if (record.revoked_at !== null) return false;
  if (Date.now() > record.expires_at) return false;
  return true;
}

function saveToken(tokenData) {
  const record = { revoked_at: null, ...tokenData };
  tokens.set(tokenData.access_token, record);
  if (tokenData.refresh_token) {
    tokens.set(record.refresh_token, record);
  }
}

function revokeToken(token) {
  const entry = tokens.get(token);
  if (!entry) return false;

  entry.revoked_at = Date.now();
  return true;
}

function getToken(token) {
  return tokens.get(token) || null;
}

function listTokens() {
  return Array.from(tokens.values()).filter(isActive);
}

module.exports = { saveToken, revokeToken, getToken, listTokens };