## Omie SDK – Visão geral

Este pacote fornece uma integração com a API do Omie preparada para:

- Uso **multi-cliente** (várias `app_key`/`app_secret` na mesma aplicação).
- Chamadas **sempre enfileiradas** via Jobs Laravel.
- **Rate limit** seguindo a documentação oficial (por IP, por App Key + método e concorrência).
- **Log completo em banco de dados** de todas as requisições/respostas.

### Instalação básica

1. Adicione o pacote via `composer` no seu projeto Laravel.
2. Publique o arquivo de configuração:

```bash
php artisan vendor:publish --tag=omie-config
```

3. Garanta que você está usando um driver de cache que suporte *locks* (ex.: `redis` ou `database`).
4. Execute as migrações para criar a tabela de logs:

```bash
php artisan migrate
```

### Configuração

O arquivo `config/omie.php` controla:

- `base_url`: endpoint base da API Omie.
- `rate_limit`: limites por IP, por App Key + método e concorrência, conforme documentação do Omie.
- `queue`: conexão e nome da fila onde os Jobs serão executados.
- `logging`: habilitar/desabilitar logs e campos sensíveis a serem mascarados.

### Uso multi-cliente

Em vez de configurar uma única `app_key` global, você passa as credenciais para cada chamada/cliente. Para produtos:

```php
use Bahiash\Omie\Services\ProdutosService;

public function criarProduto(ProdutosService $produtos)
{
    $appKey = 'APP_KEY_DO_CLIENTE';
    $appSecret = 'APP_SECRET_DO_CLIENTE';

    $produtos->dispatchCall(
        $appKey,
        $appSecret,
        'IncluirProduto',
        [
            // parâmetros conforme documentação da API de produtos Omie
        ],
        correlationId: 'opcional-para-rastreio'
    );
}
```

A chamada será:
- Enfileirada na fila configurada.
- Passará pelo `OmieRateLimiter` (IP + App Key + método + concorrência).
- Terá request/response registrados na tabela `omie_api_logs`.

### Logs em banco

A tabela `omie_api_logs` armazena:

- `app_key`, `service_path`, `method`.
- `request_body` (com campos sensíveis mascarados).
- `response_body`, `http_status`, `omie_status_code`, `omie_status_message`.
- `duration_ms`, `ip_origem`, `correlation_id`.
- Informações de erro (`error_class`, `error_message`, `error_trace`).

Você pode consultar os logs normalmente via Eloquent:

```php
use Bahiash\Omie\Models\OmieApiLog;

$logs = OmieApiLog::where('app_key', 'APP_KEY_DO_CLIENTE')
    ->where('method', 'IncluirProduto')
    ->latest()
    ->get();
```

### Rate limit

O `OmieRateLimiter` utiliza o cache para manter contadores por minuto e por método, seguindo as regras oficiais:

- Limite por IP/minuto.
- Limite por App Key + método/minuto.
- Limite de requisições simultâneas por App Key + método.

Se os limites forem excedidos, o Job aguardará a liberação dentro de um tempo máximo configurado; se o tempo se esgotar, será lançada uma `OmieRateLimitExceededException`.

