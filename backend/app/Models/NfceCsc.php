<?php

namespace App\Models;

use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class NfceCsc extends Model
{
    use HasEmpresa;

    protected $table = 'nfce_cscs';

    protected $fillable = ['empresa_id', 'ambiente', 'identificador', 'codigo_criptografado', 'ativo'];

    protected $casts = [
        'ambiente' => 'integer',
        'ativo' => 'boolean',
    ];

    protected $hidden = ['codigo_criptografado'];

    protected $appends = ['codigo_mascarado', 'ambiente_nome'];

    public function setCodigoAttribute(string $value): void
    {
        $this->attributes['codigo_criptografado'] = Crypt::encryptString($value);
    }

    public function getCodigoAttribute(): ?string
    {
        if (empty($this->attributes['codigo_criptografado'])) return null;
        return Crypt::decryptString($this->attributes['codigo_criptografado']);
    }

    public function getCodigoMascaradoAttribute(): string
    {
        $codigo = $this->codigo;
        if (!$codigo) return '';
        if (strlen($codigo) <= 8) return str_repeat('•', strlen($codigo));
        return substr($codigo, 0, 4) . str_repeat('•', max(4, strlen($codigo) - 8)) . substr($codigo, -4);
    }

    public function getAmbienteNomeAttribute(): string
    {
        return $this->ambiente === 1 ? 'Produção' : 'Homologação';
    }
}
