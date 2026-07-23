<?php
namespace App\Models;
use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OsStatus extends Model
{
    use HasEmpresa;
    public $timestamps = false;

    protected $table = 'os_status';
    protected $fillable = ['empresa_id','nome','cor','ordem','tipo'];

    public function ordensServico(): HasMany
    {
        return $this->hasMany(OrdemServico::class, 'status_id');
    }
}
