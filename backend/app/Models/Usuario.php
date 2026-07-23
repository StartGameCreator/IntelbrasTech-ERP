<?php

namespace App\Models;
use App\Traits\HasUuid;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Usuario extends Authenticatable
{
    use HasApiTokens, Notifiable, HasUuid;

    protected $table = 'usuarios';

    protected $fillable = [
        'empresa_id','perfil_id','nome','email','senha',
        'avatar','telefone','cargo','ativo','tema','preferencias',
    ];

    protected $hidden = ['senha', 'remember_token', 'token_recuperacao'];

    protected $casts = [
        'ativo'          => 'boolean',
        'preferencias'   => 'array',
        'ultimo_acesso'  => 'datetime',
        'email_verificado_em' => 'datetime',
    ];

    // Laravel usa 'password' internamente, mapeamos 'senha'
    public function getAuthPassword(): string
    {
        return $this->senha;
    }

    // ── Relações ──────────────────────────────────────────────

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function perfil(): BelongsTo
    {
        return $this->belongsTo(Perfil::class);
    }

    public function notificacoes(): HasMany
    {
        return $this->hasMany(Notificacao::class);
    }

    // ── Permissões ────────────────────────────────────────────

    public function temPermissao(string $modulo, string $acao): bool
    {
        if ($this->perfil?->admin) return true;

        return $this->perfil
            ?->permissoes()
            ->where('modulo', $modulo)
            ->where('acao', $acao)
            ->exists() ?? false;
    }

    public function pode(string $permissao): bool
    {
        [$modulo, $acao] = explode('.', $permissao, 2);
        return $this->temPermissao($modulo, $acao);
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    // ── Helpers ───────────────────────────────────────────────

    public function registrarAcesso(): void
    {
        $this->timestamps = false;
        $this->update(['ultimo_acesso' => now()]);
        $this->timestamps = true;
    }
}
