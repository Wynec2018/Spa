<?php

return [
    'name' => getenv('APP_NAME') ?: 'sistema',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'charset' => 'utf-8',
];
