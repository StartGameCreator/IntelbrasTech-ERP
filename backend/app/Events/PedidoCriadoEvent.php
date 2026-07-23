<?php
namespace App\Events;
use App\Models\Pedido;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PedidoCriadoEvent
{
    use Dispatchable, SerializesModels;
    public function __construct(public readonly Pedido $pedido) {}
}
