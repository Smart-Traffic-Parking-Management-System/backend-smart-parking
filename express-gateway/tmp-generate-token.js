const jwt = require('jsonwebtoken');
const secret = 'f64b8849028ed4ebe0d1c57a81501493428f5a30c86ea66880334d002a0adaab3d0861da87af38196f181c6005a83cf9b6b533d9d6e76500ca8c100bcf51ff95';
const payload = { user_id: 1, username: 'admin', email: 'admin@smartcity.local', role: 'admin', scope: 'read write' };
console.log(jwt.sign(payload, secret, { expiresIn: 3600 }));
