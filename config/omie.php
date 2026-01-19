<?php

return [
    'app_key' => env('OMIE_APP_KEY'),
    'app_secret' => env('OMIE_APP_SECRET'),
    'base_url' => env('OMIE_BASE_URL', 'https://app.omie.com.br/api/v1/'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limit (Regras OMIE)
    |--------------------------------------------------------------------------
    | - 960 requisições/minuto por IP
    | - 240 requisições/minuto por IP + App Key + Método
    | - 4 requisições simultâneas por IP + App Key + Método
    | - Métodos restritos: apenas 1 requisição por vez (configurar em restricted_methods)
    */
    'rate_limit' => [
        'per_ip_per_minute' => 960,
        'per_method_per_minute' => 240,
        'concurrent_per_method' => 4,
        'restricted_methods' => [], // Ex: ['IncluirAjusteEstoque'] – apenas 1 por vez
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache (evitar consultas redundantes em menos de 60 segundos)
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'ttl_seconds' => 60,
        'listar_methods' => ['ListarAjusteEstoque'], // Métodos de listagem que usam cache
    ],
];
