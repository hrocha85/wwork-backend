<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | O PWA vive em outro host (app.) e chama a API (api.) com o cookie de
    | sessão. Para o navegador mandar esse cookie, três coisas precisam estar
    | certas ao mesmo tempo: supports_credentials verdadeiro, a origem listada
    | em allowed_origins e a rota coberta por paths.
    |
    | Curinga '*' em allowed_origins é rejeitado pelo navegador quando há
    | credenciais. As origens vêm do .env, uma por linha de vírgula.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000'))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
