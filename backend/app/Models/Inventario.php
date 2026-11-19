<?php
namespace App\Models;

use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Inventario extends Model
{
    use HasEmpresa;

    protected $table = 'inventarios';
    protected $fillable = [
        'empresa_id', 'deposito_id', 'numero', 'data_inicio', 'data_fim',
        'status', 'responsavel_id', 'observacoes',
    ];

    protected $casts = [
        'data_inicio' => 'datetime',
        'data_fim'    => 'datetime',
    ];

    protected $appends = ['pode_editar'];

    const STATUS_ABERTO       = 'aberto';
    const STATUS_EM_ANDAMENTO = 'em_andamento';
    const STATUS_FINALIZADO   = 'finalizado';
    const STATUS_CANCELADO    = 'cancelado';

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(Deposito::class);
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsavel_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(InventarioItem::class);
    }

    public function getPodeEditarAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_ABERTO, self::STATUS_EM_ANDAMENTO]);
    }
}
