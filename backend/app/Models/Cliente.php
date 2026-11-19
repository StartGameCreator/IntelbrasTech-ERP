<?php

namespace App\Models;
use App\Traits\HasUuid;

use App\Traits\HasEmpresa;
use App\Traits\HasAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Cliente extends Model
{
    use HasEmpresa, HasAuditoria, SoftDeletes, HasUuid;

    protected $fillable = [
        'empresa_id','tipo','razao_social','nome_fantasia','cpf_cnpj',
        'rg_ie','email','email_nfe','telefone','celular','whatsapp',
        'site','inscricao_estadual','inscricao_municipal','contribuinte_icms',
        'data_nascimento','sexo','profissao','estado_civil',
        'limite_credito','desconto_padrao','tabela_preco_id','vendedor_id',
        'tags','observacoes','foto','ativo','bloqueado','motivo_bloqueio',
        'cep','logradouro','numero','complemento','bairro','cidade','estado','ibge',
        'latitude','longitude','origem','canal_aquisicao',
    ];

    protected $casts = [
        'contribuinte_icms' => 'boolean',
        'ativo'             => 'boolean',
        'bloqueado'         => 'boolean',
        'limite_credito'    => 'decimal:2',
        'desconto_padrao'   => 'decimal:2',
        'latitude'          => 'decimal:8',
        'longitude'         => 'decimal:8',
        'data_nascimento'   => 'date',
        'tags'              => 'array',
    ];

    // ── Relações ──────────────────────────────────────────────

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'vendedor_id');
    }

    public function tabelaPreco(): BelongsTo
    {
        return $this->belongsTo(TabelaPreco::class);
    }

    public function enderecos(): HasMany
    {
        return $this->hasMany(ClienteEndereco::class);
    }

    public function contatos(): HasMany
    {
        return $this->hasMany(ClienteContato::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    public function contasReceber(): HasMany
    {
        return $this->hasMany(ContaReceber::class);
    }

    public function ordensServico(): HasMany
    {
        return $this->hasMany(OrdemServico::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopeAtivos(Builder $q): Builder
    {
        return $q->where('ativo', true);
    }

    public function scopePessoaFisica(Builder $q): Builder
    {
        return $q->where('tipo', 'F');
    }

    public function scopePessoaJuridica(Builder $q): Builder
    {
        return $q->where('tipo', 'J');
    }

    public function scopeBusca(Builder $q, string $termo): Builder
    {
        return $q->where(function ($q) use ($termo) {
            $q->whereLike('razao_social', "%{$termo}%")
              ->orWhereLike('nome_fantasia', "%{$termo}%")
              ->orWhere('cpf_cnpj', 'like', "%{$termo}%")
              ->orWhereLike('email', "%{$termo}%")
              ->orWhere('celular', 'like', "%{$termo}%");
        });
    }

    public function scopeAniversariantes(Builder $q, ?int $mes = null): Builder
    {
        $mes = $mes ?? now()->month;
        return $q->whereMonth('data_nascimento', $mes);
    }

    public function scopeComGeo(Builder $q): Builder
    {
        return $q->whereNotNull('latitude')->whereNotNull('longitude');
    }

    // ── Accessors ─────────────────────────────────────────────

    public function getNomeAttribute(): string
    {
        return $this->nome_fantasia ?: $this->razao_social;
    }

    public function getDocumentoFormatadoAttribute(): string
    {
        $doc = preg_replace('/\D/', '', $this->cpf_cnpj ?? '');
        if (strlen($doc) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc);
        }
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $doc);
    }

    public function getStatusFinanceiroAttribute(): array
    {
        $totalAberto  = $this->contasReceber()->where('status', 'aberto')->sum('valor');
        $totalAtrasado = $this->contasReceber()->where('status', 'aberto')
                             ->where('data_vencimento', '<', today())->sum('valor');
        return [
            'total_aberto'   => $totalAberto,
            'total_atrasado' => $totalAtrasado,
            'bloqueado'      => $this->bloqueado,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────

    public function possuiLimiteDisponivel(float $valor): bool
    {
        if ($this->limite_credito <= 0) return true;
        $emAberto = $this->contasReceber()->whereIn('status', ['aberto', 'parcial'])->sum('valor');
        return ($emAberto + $valor) <= $this->limite_credito;
    }
}
