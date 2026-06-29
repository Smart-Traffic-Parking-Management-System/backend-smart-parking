<?php

return [
    'host'     => $_ENV['DB_HOST'] ?? 'mysql',
    'port'     => $_ENV['DB_PORT'] ?? '3306',
    'database' => $_ENV['DB_NAME'] ?? 'smartcity',
    'user'     => $_ENV['DB_USER'] ?? 'smartcity_app',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
];
