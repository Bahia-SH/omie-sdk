<?php

namespace Bahiash\Omie\Tests;

use Bahiash\Omie\Jobs\DispatchOmieCallJob;
use Bahiash\Omie\Services\ProdutosService;
use Illuminate\Support\Facades\Queue;

class ProdutosServiceTest extends TestCase
{
    public function test_dispatch_call_enfileira_dispatch_omie_call_job(): void
    {
        Queue::fake();

        $service = new ProdutosService();
        $service->dispatchCall(
            'my-app-key',
            'my-app-secret',
            'ListarProdutos',
            ['pagina' => 1, 'registros_por_pagina' => 50],
            'App\Events\OmieProductsListed',
            ['user_id' => 123]
        );

        Queue::assertPushed(DispatchOmieCallJob::class, function (DispatchOmieCallJob $job) {
            return $job->appKey === 'my-app-key'
                && $job->appSecret === 'my-app-secret'
                && $job->servicePath === 'geral/produtos'
                && $job->method === 'ListarProdutos'
                && $job->params === ['pagina' => 1, 'registros_por_pagina' => 50]
                && $job->eventClass === 'App\Events\OmieProductsListed'
                && $job->eventParams === ['user_id' => 123];
        });
    }

    public function test_dispatch_call_sem_event_class_e_params(): void
    {
        Queue::fake();

        $service = new ProdutosService();
        $service->dispatchCall('key', 'secret', 'IncluirProduto', ['descricao' => 'Produto X']);

        Queue::assertPushed(DispatchOmieCallJob::class, function (DispatchOmieCallJob $job) {
            return $job->eventClass === null && $job->eventParams === [];
        });
    }
}
