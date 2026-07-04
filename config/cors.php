<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Autorise les requêtes cross-origin depuis le mobile Flutter (Android/iOS)
    | et le frontend web (Nuxt).
    |
    | En développement, 'allowed_origins' accepte '*' pour simplifier.
    | En production, remplacez '*' par les domaines réels.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
    | Important : mettre à false pour les API mobiles (tokens Bearer).
    | Mettre à true uniquement pour une SPA avec cookies Sanctum.
    */
    'supports_credentials' => false,

];
