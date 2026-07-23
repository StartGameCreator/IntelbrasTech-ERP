<?php
namespace App\Events;
use App\Models\NotaFiscal;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NFeAutorizadaEvent
{
    use Dispatchable, SerializesModels;
    public function __construct(public readonly NotaFiscal $notaFiscal) {}
}
