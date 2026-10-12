<?php

$password = getenv('DB_PASSWORD');

return [
    'host' => envRequired('DB_HOST'),
    'port' => envRequired('DB_PORT'),
    'database' => envRequired('DB_NAME'),
    'user' => envRequired('DB_USER'),
    'password' => $password === false ? '' : $password,
    'charset' => 'utf8mb4',
];
