<?php

declare(strict_types=1);

return [
    'driver' => 'pgsql',
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('DB_PORT') ?: 5432),
    'name' => getenv('DB_NAME') ?: 'spa_cielo',
    'user' => getenv('DB_USER') ?: 'spa_readonly',
    'password' => getenv('DB_PASSWORD') ?: '',
    'sslmode' => getenv('DB_SSLMODE') ?: 'prefer',
];
