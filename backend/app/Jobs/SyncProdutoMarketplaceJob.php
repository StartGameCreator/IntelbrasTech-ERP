<?php
namespace App\Jobs;

use App\Models\Integracao;
use App\Services\Integracao\MercadoLivreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

class SyncProdutoMarketplaceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly Integracao $integracao,
        public readonly string $itemId,
    ) {}

    public function handle(): void
    {
        // Um único anúncio mudou (webhook "items"); reaproveita a sincronização
        // completa de produtos, que já é idempotente (updateOrCreate por marketplace_id).
        match ($this->integracao->tipo) {
            'mercadolivre' => (new MercadoLivreService($this->integracao))->sincronizarProdutos(),
            'shopee'       => (new \App\Services\Integracao\ShopeeService($this->integracao))->sincronizarProdutos(),
            default        => null,
        };
    }
}
