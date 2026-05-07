<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Endpoint base da API Omie
    |--------------------------------------------------------------------------
    |
    | As credenciais (app_key e app_secret) NÃO devem ser configuradas aqui.
    | Elas são passadas por instância de cliente (multi-tenant).
    */
    'base_url' => env('OMIE_BASE_URL', 'https://app.omie.com.br/api/v1/'),

    /*
    |--------------------------------------------------------------------------
    | Configurações HTTP (Guzzle)
    |--------------------------------------------------------------------------
    */
    'http' => [
        'timeout' => (int) env('OMIE_HTTP_TIMEOUT', 30),
        'connect_timeout' => (int) env('OMIE_HTTP_CONNECT_TIMEOUT', 10),
        // Retry interno do client. Desligado por padrão pois a fila já faz retry
        // (omie.queue.tries + backoff). Habilite só se rodar fora de fila.
        'retry' => [
            'enabled' => (bool) env('OMIE_HTTP_RETRY_ENABLED', false),
            'max_attempts' => (int) env('OMIE_HTTP_RETRY_MAX', 3),
            'base_delay_ms' => (int) env('OMIE_HTTP_RETRY_DELAY_MS', 500),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limit (Regras OMIE)
    |--------------------------------------------------------------------------
    | - 960 requisições/minuto por IP
    | - 240 requisições/minuto por App Key + Método
    | - 4 requisições simultâneas por App Key + Método
    | - strategy: 'fixed' (janela fixa por minuto) | 'sliding' (Redis sorted set)
    */
    'rate_limit' => [
        'strategy' => env('OMIE_RATE_STRATEGY', 'fixed'),

        'per_ip_per_minute' => 960,
        'per_app_method_per_minute' => 240,
        'concurrent_per_app_method' => 4,

        'max_wait_seconds' => 70,
        'max_wait_concurrent_seconds' => 120,

        // Métodos extremamente restritos podem ter lógica adicional
        'restricted_methods' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fila para chamadas à API Omie
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'connection' => env('OMIE_QUEUE_CONNECTION'),
        'queue' => env('OMIE_QUEUE', 'default'),
        'tries' => (int) env('OMIE_QUEUE_TRIES', 5),
        'backoff' => [10, 30, 60, 120, 300],
    ],

    /*
    |--------------------------------------------------------------------------
    | Modo síncrono
    |--------------------------------------------------------------------------
    | Permite que `*Sync` methods executem dispatchSync ignorando a fila.
    */
    'sync' => [
        'enabled' => (bool) env('OMIE_SYNC_ENABLED', true),
        'wait_timeout_seconds' => (int) env('OMIE_SYNC_WAIT_TIMEOUT', 30),
        'wait_poll_ms' => (int) env('OMIE_SYNC_WAIT_POLL_MS', 250),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging de requisições/respostas da API Omie
    |--------------------------------------------------------------------------
    | masked_fields: lista de chaves (em qualquer profundidade) que serão
    | substituídas por *** ao gravar request/response/event_params no log.
    */
    'logging' => [
        'enabled' => env('OMIE_LOG_ENABLED', true),
        'mask_response' => (bool) env('OMIE_LOG_MASK_RESPONSE', true),
        'masked_fields' => [
            'app_secret',
            'password',
            'token',
            'authorization',
            'cnpj_cpf',
            'cpf',
            'cnpj',
            'rg',
            'cartao_credito',
            'numero_cartao',
            'cvv',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Eventos
    |--------------------------------------------------------------------------
    | legacy_single_event: se true, dispatcha o eventClass único informado
    | em construtor (sucesso e erro) — comportamento legado.
    */
    'events' => [
        'legacy_single_event' => (bool) env('OMIE_EVENTS_LEGACY', true),
    ],
];
