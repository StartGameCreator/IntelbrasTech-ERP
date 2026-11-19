<?php
namespace App\Models;
use App\Traits\HasUuid;
use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fornecedor extends Model
{
    use HasEmpresa, HasUuid;
    protected $table = 'fornecedores';
    protected $fillable = ['empresa_id','tipo','razao_social','nome_fantasia','cpf_cnpj','ie','email','telefone','celular','whatsapp','site','cep','logradouro','numero','complemento','bairro','cidade','estado','prazo_entrega_dias','prazo_pagamento_dias','desconto_padrao','avaliacao','banco','agencia','conta','pix','contato_principal','observacoes','ativo'];

    protected $casts = [
        'desconto_padrao' => 'decimal:2',
        'avaliacao'       => 'integer',
        'ativo'           => 'boolean',
    ];

    public function scopeBusca(Builder $q, string $termo): Builder
    {
        return $q->where(function ($qq) use ($termo) {
            $qq->whereLike('razao_social', "%{$termo}%")
               ->orWhereLike('nome_fantasia', "%{$termo}%")
               ->orWhereLike('cpf_cnpj', "%{$termo}%")
               ->orWhereLike('email', "%{$termo}%");
        });
    }

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class);
    }

    public function contasPagar(): HasMany
    {
        return $this->hasMany(ContaPagar::class);
    }

    public function pedidosCompra(): HasMany
    {
        return $this->hasMany(PedidoCompra::class);
    }
}
