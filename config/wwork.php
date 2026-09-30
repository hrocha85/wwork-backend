<?php

return [

    /*
    | Senhas do seed. Vazias no git. O seeder recusa correr sem elas.
    | Fora de production a demo (owner@ / invited@) entra. Em production
    | só entra se SEED_DEMO=true.
    */

    'admin_seed_password' => env('ADMIN_SEED_PASSWORD'),

    'demo_seed_password' => env('DEMO_SEED_PASSWORD'),

    'seed_demo' => (bool) env('SEED_DEMO', false),

    'frontend_url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/'),

    'onesignal_app_id' => env('ONESIGNAL_APP_ID'),

    'onesignal_rest_key' => env('ONESIGNAL_REST_API_KEY'),

    /*
    | Último dia em que o mensal entra com o preço de lançamento por 12 meses.
    | O anual com desconto no 1º ano não depende desta data.
    */
    'launch_offer_ends_at' => env('LAUNCH_OFFER_ENDS_AT', '2027-03-31'),

];
