<?php

$allowedOrigins = array_filter(array_map(
    static fn (string $origin) => trim($origin),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))
));

if (empty($allowedOrigins)) {
    $allowedOrigins = ['*'];
}

return [
    'paths' => ['api/*', 'login'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];


