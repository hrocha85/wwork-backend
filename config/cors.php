<?php

$origins = array_values(array_filter(array_map(
    trim(...),
    explode(',', (string) env('FRONTEND_URL', 'http://localhost:3000')),
)));

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins === [] ? ['http://localhost:3000'] : $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
