<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Endpoint base da API Omie
    |--------------------------------------------------------------------------
    |
    | As credenciais (app_key e app_secret) NÃO devem ser configuradas aqui.
    | Elas serão passadas por instância de cliente (multi-cliente).
    */
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
        // Limite por IP (documentação Omie)
        'per_ip_per_minute' => 960,

        // Limite por combinação App Key + Método (documentação Omie)
        'per_app_method_per_minute' => 240,

        // Limite de requisições simultâneas por App Key + Método
        'concurrent_per_app_method' => 4,

        // Métodos extremamente restritos podem ter lógica adicional, se necessário
        'restricted_methods' => [], // Ex.: ['IncluirAjusteEstoque']
    ],

    /*
    |--------------------------------------------------------------------------
    | Fila para chamadas à API Omie
    |--------------------------------------------------------------------------
    */
    'queue' => [
        // Conexão de fila a ser usada (null = conexão padrão do Laravel)
        'connection' => env('OMIE_QUEUE_CONNECTION'),

        // Nome da fila (queue) para os jobs de chamadas Omie
        'queue' => env('OMIE_QUEUE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging de requisições/respostas da API Omie
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('OMIE_LOG_ENABLED', true),

        // Campos sensíveis que serão mascarados no log (ex.: app_secret)
        'masked_fields' => [
            'app_secret',
        ],
    ],
];
