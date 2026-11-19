<?php
namespace App\Services\Crm;
use App\Models\{CrmAtividade,Lead};
use Illuminate\Support\Facades\DB;
class CrmAutomationService {
 public function executar(string $gatilho,Lead $lead):void{
  $rules=DB::table('crm_automacoes')->where('empresa_id',$lead->empresa_id)->where('gatilho',$gatilho)->where('ativo',true)->get();
  foreach($rules as $rule){$cfg=json_decode($rule->configuracao?:'{}',true);if($rule->acao==='criar_tarefa'){$days=max(0,(int)($cfg['dias']??1));CrmAtividade::create(['empresa_id'=>$lead->empresa_id,'lead_id'=>$lead->id,'usuario_id'=>$lead->responsavel_id,'tipo'=>'tarefa','titulo'=>$cfg['titulo']??'Realizar acompanhamento','status'=>'pendente','agendada_para'=>now()->addDays($days)]);if(!$lead->proxima_acao_em)$lead->update(['proxima_acao'=>$cfg['titulo']??'Realizar acompanhamento','proxima_acao_em'=>now()->addDays($days)]);}DB::table('crm_automacoes')->where('id',$rule->id)->update(['execucoes'=>DB::raw('execucoes + 1'),'ultima_execucao_em'=>now(),'updated_at'=>now()]);}
 }
}
