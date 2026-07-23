<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotaFiscalItem extends Model
{
    protected $table = 'nota_fiscal_itens';
    protected $fillable = ['nota_fiscal_id','produto_id','numero_item','codigo','descricao','ncm','cfop','unidade','quantidade','valor_unitario','valor_total','valor_desconto','icms_origem','icms_cst','icms_base','icms_aliquota','icms_valor','ipi_cst','ipi_base','ipi_aliquota','ipi_valor','pis_cst','pis_base','pis_aliquota','pis_valor','cofins_cst','cofins_base','cofins_aliquota','cofins_valor'];
}
