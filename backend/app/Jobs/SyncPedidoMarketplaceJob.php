<?php
namespace App\Jobs;
use App\Models\Integracao;
use App\Services\Integracao\MercadoLivreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

class SyncPedidoMarketplaceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly Integracao $integracao,
        public readonly string $pedidoId,
        public readonly string $marketplace
    ) {}

    public function handle(): void
    {
        match ($this->marketplace) {
            'mercadolivre' => (new MercadoLivreService($this->integracao))->sincronizarPedidos(),
            'shopee'       => (new \App\Services\Integracao\ShopeeService($this->integracao))->sincronizarPedidos(),
            default        => null,
        };
    }
}
