<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedidoItem extends Model
{
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $table = 'pedido_itens';
    protected $fillable = ['pedido_id','produto_id','variacao_id','deposito_id','codigo','descricao','ncm','cfop','unidade','quantidade','preco_unitario','desconto_percentual','desconto_valor','total','icms_cst','icms_aliquota','pis_cst','pis_aliquota','cofins_cst','cofins_aliquota'];
}
