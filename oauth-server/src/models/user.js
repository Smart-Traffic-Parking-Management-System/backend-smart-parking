const users = new Map();
const revokedTokens = new Map();

function initializeAdminUser() {
  const bcrypt = require('bcryptjs');
  const adminUsername = process.env.ADMIN_USERNAME || 'admin';
  const adminPassword = process.env.ADMIN_PASSWORD || 'admin@123';
  const adminEmail = process.env.ADMIN_EMAIL || 'admin@smartcity.local';

  users.set(adminUsername, {
    id: 1,
    username: adminUsername,
    email: adminEmail,
    password_hash: bcrypt.hashSync(adminPassword, 10),
    role: 'admin',
    created_at: new Date(),
    is_active: true,
  });
}

function getUserByUsername(username) {
  return users.get(username) || null;
}

function getUserByEmail(email) {
  for (const user of users.values()) {
    if (user.email === email) {
      return user;
    }
  }
  return null;
}

function createUser(username, email, passwordHash, role = 'citizen') {
  if (getUserByUsername(username) || getUserByEmail(email)) {
    return null; // Duplicate user
  }

  const userId = Math.max(...Array.from(users.values()).map(u => u.id), 0) + 1;

  const newUser = {
    id: userId,
    username,
    email,
    password_hash: passwordHash,
    role,
    created_at: new Date(),
    is_active: true,
  };

  users.set(username, newUser);
  return { ...newUser, password_hash: undefined }; // Hide password
}


function revokeToken(token) {
  revokedTokens.set(token, {
    token,
    revoked_at: Date.now(),
  });
}

function isTokenRevoked(token) {
  return revokedTokens.has(token);
}


function listAllUsers() {
  return Array.from(users.values()).map(u => ({
    id: u.id,
    username: u.username,
    email: u.email,
    role: u.role,
    created_at: u.created_at,
  }));
}

initializeAdminUser();

module.exports = {
  getUserByUsername,
  getUserByEmail,
  createUser,
  revokeToken,
  isTokenRevoked,
  listAllUsers,
};
