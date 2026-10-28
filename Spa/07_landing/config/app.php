<?php

declare(strict_types=1);

return [
    'name' => 'Spa Cielo',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
    'url' => getenv('APP_URL') ?: 'http://localhost:8087',
    'timezone' => 'America/La_Paz',
];
