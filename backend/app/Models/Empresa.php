<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Empresa extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid','razao_social','nome_fantasia','cnpj','ie','im',
        'email','telefone','celular','cep','logradouro','numero',
        'complemento','bairro','cidade','estado','ibge',
        'regime_tributario','regime_apuracao','regime_fiscal_confirmado','regime_fiscal_confirmado_em','icms_csosn_padrao','icms_cst_padrao','pis_cst_padrao','cofins_cst_padrao','fiscal_observacao','cnae','site','logo',
        'ativo','plano','trial_ate',
        'inscricoes_substitutas',
        'contato_nome',
        'segmentos',
        'quantidade_funcionarios',
        'faturamento_anual_faixa',
        'porte_empresa',
        'atividade_principal',
        'ie_isento',
        'tipo_pessoa',
        'cadastro_pendente',
        'cadastro_concluido_em',
        'dados_fonte',
    ];

    protected $appends = ['logo_url'];

    protected $casts = [
        'ativo'     => 'boolean',
        'trial_ate' => 'date',
        'regime_tributario' => 'integer',
        'regime_fiscal_confirmado' => 'boolean',
        'regime_fiscal_confirmado_em' => 'datetime',
        'ie_isento' => 'boolean',
        'segmentos' => 'array',
        'cadastro_pendente' => 'boolean',
        'cadastro_concluido_em' => 'datetime',
        'inscricoes_substitutas' => 'array',
    ];


    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        if (str_starts_with((string) $this->logo, 'http://') || str_starts_with((string) $this->logo, 'https://')) {
            return (string) $this->logo;
        }

        $caminho = str_replace('\\', '/', ltrim((string) $this->logo, '/'));

        if (! Storage::disk('public')->exists($caminho)) {
            return null;
        }

        return asset('storage/' . $caminho);
    }

    /**
     * Retorna a logo embutida no PDF, evitando falhas do DomPDF ao acessar
     * URLs locais, links simbólicos ou servidores diferentes.
     */
    public function getLogoDataUriAttribute(): ?string
    {
        if (! $this->logo || ! Storage::disk('public')->exists((string) $this->logo)) {
            return null;
        }

        $path = (string) $this->logo;
        $contents = Storage::disk('public')->get($path);
        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    // ── Relações ──────────────────────────────────────────────

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    // ── Helpers ───────────────────────────────────────────────

    public function emTrialOuAtiva(): bool
    {
        if ($this->plano === 'trial') {
            return $this->trial_ate?->isFuture() ?? false;
        }
        return $this->ativo;
    }

    public function cnpjFormatado(): string
    {
        $c = preg_replace('/\D/', '', (string) $this->cnpj);
        if (strlen($c) !== 14) return '';
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $c);
    }
}
