<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Moeda
    |--------------------------------------------------------------------------
    |
    | GBP. Valores de dinheiro são gravados em pence inteiros, nunca em ponto
    | flutuante. Uma agência nasce com esta moeda.
    |
    */

    'currency' => env('WWORK_CURRENCY', 'GBP'),

    /*
    |--------------------------------------------------------------------------
    | Fuso padrão de uma agência
    |--------------------------------------------------------------------------
    |
    | O lançamento é Londres. Dia e hora da visita seguem este fuso.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'Europe/London'),

    /*
    |--------------------------------------------------------------------------
    | Línguas da interface
    |--------------------------------------------------------------------------
    |
    | Inglês completo primeiro. As outras quatro são as do campo em Londres.
    |
    */

    'locales' => ['en', 'pt', 'pl', 'ro', 'es'],

    /*
    |--------------------------------------------------------------------------
    | Primeiro fundador
    |--------------------------------------------------------------------------
    |
    | Lido pelo FounderSeeder. Sem e-mail e senha no .env, o seeder não cria
    | ninguém: não se comita credencial de acesso ao /admin.
    |
    */

    'founder' => [
        'name' => env('WWORK_FOUNDER_NAME', 'WWork Founder'),
        'email' => env('WWORK_FOUNDER_EMAIL'),
        'password' => env('WWORK_FOUNDER_PASSWORD'),
    ],

];
