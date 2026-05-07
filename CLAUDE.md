# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Language & Docs

- Always respond in **Portuguese (pt-BR)** — per `.cursor/rules/omie-sdk-guidance.mdc`.
- Source-of-truth docs:
  - Laravel 12.x: https://laravel.com/docs/12.x
  - Omie API rate limits: https://ajuda.omie.com.br/pt-BR/articles/8112984-limites-de-consumo-da-api-do-omie
  - Omie API error handling: https://ajuda.omie.com.br/pt-BR/articles/8001888-tratando-os-erros-de-api
  - Omie APIs/Webhooks: https://ajuda.omie.com.br/pt-BR/collections/3045828-apis-e-webhooks

## Commands

```bash
composer install
vendor/bin/phpunit                                  # full suite
vendor/bin/phpunit tests/OmieClientTest.php         # single file
vendor/bin/phpunit --filter testMethodName          # single test
```

No linter/static analysis configured. PHP `^8.1`, PHPUnit `^10`, Laravel `^9|^10|^11|^12`.

## Architecture

Laravel package (`Bahiash\Omie\`, PSR-4 → `src/`) auto-registered via `extra.laravel.providers` → `OmieServiceProvider`.

Call flow (always async, always logged, always rate-limited):

```
Service (e.g. ProdutosService)
  └─ DispatchOmieCallJob::dispatch(appKey, appSecret, servicePath, method, params, eventClass?, eventParams?)
       └─ queue worker → DispatchOmieCallJob::handle()
            ├─ OmieApiLogger::startLog          (insert OmieApiLog row, mask sensitive fields)
            ├─ OmieRateLimiter::checkOrWait     (cache-locked counters per IP / app+method / concurrent)
            ├─ new OmieClient(...)->call()      (Guzzle POST {app_key, app_secret, call, param})
            ├─ OmieApiLogger::finishLogSuccess|finishLogError
            └─ Event::dispatch(new $eventClass($log, $eventParams))   if eventClass set
```

Key invariants:

- **Multi-tenant by design**: `app_key`/`app_secret` are NEVER global config — always passed per call. `OmieClient` is constructed inside the job, not via DI.
- **All API calls are queued.** No synchronous path. Queue connection/name come from `config('omie.queue')` and are applied in the job constructor.
- **Cache must support locks** (redis/database). `OmieRateLimiter` uses `$cache->lock()` to atomically increment per-minute counters and a concurrent-call counter; on timeout throws `OmieRateLimitExceededException`.
- **Logging is mandatory and DB-backed.** Migration `database/migrations/2026_02_10_000000_create_omie_api_logs_table.php` creates `omie_api_logs`. Sensitive fields listed in `config('omie.logging.masked_fields')` are replaced with `***` before insert.
- **Event hook is opt-in.** If `eventClass` omitted, no event fires. When set, the same event class is dispatched for both success and error — consumer must inspect `OmieApiLog` state.
- **Param shape**: `OmieClient::normalizeParam` wraps a non-list array in `[$param]` to match Omie's expected `param[0]` envelope; lists pass through.

Adding a new Omie resource → mirror `ProdutosService`: a thin class with a `SERVICE_PATH` const that delegates to `DispatchOmieCallJob::dispatch`. Register as singleton in `OmieServiceProvider::register()`.

## Config (`config/omie.php`)

Published via `php artisan vendor:publish --tag=omie-config`. Migrations auto-loaded by the provider when running in console. Defaults match Omie's documented limits: 960/min per IP, 240/min per app+method, 4 concurrent per app+method.

## Tests

`tests/TestCase.php` extends Orchestra Testbench. Stubs in `tests/Stubs/`. Coverage spans `OmieClient`, `OmieRateLimiter`, `DispatchOmieCallJob`, `ProdutosService`, `OmieApiLogger`, `OmieApiLog` model, exceptions, `OmieServiceProvider`.
