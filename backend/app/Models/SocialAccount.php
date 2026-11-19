<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends Model
{
    protected $fillable = [
        'empresa_id','usuario_id','provider','provider_user_id','email',
        'access_token','refresh_token','expires_at','scopes','metadata','ativo',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
        'scopes' => 'array',
        'metadata' => 'array',
        'ativo' => 'boolean',
    ];

    public function usuario(): BelongsTo { return $this->belongsTo(Usuario::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
}
