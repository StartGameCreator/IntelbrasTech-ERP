<?php
namespace App\Models;
use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacao extends Model
{
    use HasEmpresa;
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $table = 'notificacoes';
    protected $fillable = ['empresa_id','usuario_id','tipo','titulo','mensagem','icone','cor','lida','url','dados'];

    protected $casts = [
        'lida'  => 'boolean',
        'dados' => 'array',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
