<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    */
     

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'register', '*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://carerp.io',
        'https://www.carerp.io',
        'https://e-carstech.com', 
        'https://www.e-carstech.com',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:8000'
    ],
    // 'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')),
    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'Authorization', 
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'Accept',
        'Origin',
        'X-API-KEY',
        'X-Tenant-Domain' 
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];