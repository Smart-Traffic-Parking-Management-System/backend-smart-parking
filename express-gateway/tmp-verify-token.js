const jwt = require('jsonwebtoken');
const token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjoxLCJ1c2VybmFtZSI6ImFkbWluIiwidXNlcm5hbWUiOiJhZG1pbiIsImVtYWlsIjoiYWRtaW5Ac21hcnRjaXR5LmxvY2FsIiwicm9sZSI6ImFkbWluIiwic2NvcGUiOiJyZWFkIHdyaXRlIiwiaWF0IjoxNzgyNjg3MzQzLCJleHAiOjE3ODI2OTA5NDN9.4rP3QfIiZhunE2x6x5Hw5bQFalZQi9HUy7wEd6g4sVU';
const secret = 'f64b8849028ed4ebe0d1c57a81501493428f5a30c86ea66880334d002a0adaab3d0861da87af38196f181c6005a83cf9b6b533d9d6e76500ca8c100bcf51ff95';
try {
  const decoded = jwt.verify(token, secret);
  console.log(JSON.stringify(decoded));
} catch (error) {
  console.error(error.message);
  process.exit(1);
}
