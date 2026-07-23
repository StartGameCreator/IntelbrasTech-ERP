<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Vendas do PDV</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">Histórico, comprovantes e preparação para NFC-e.</p>
      </div>
      <RouterLink to="/pdv" class="btn-primary">Abrir Frente de Caixa</RouterLink>
    </div>

    <div class="card p-4">
      <div class="grid gap-3 md:grid-cols-5">
        <input v-model="filtros.busca" class="input md:col-span-2" placeholder="Venda, cliente, documento ou comprovante"/>
        <input v-model="filtros.data_inicio" type="date" class="input"/>
        <input v-model="filtros.data_fim" type="date" class="input"/>
        <button class="btn-secondary" @click="carregar(1)">Pesquisar</button>
      </div>
    </div>

    <div class="card overflow-hidden">
      <div v-if="loading" class="p-10 text-center text-sm text-[var(--text-muted)]">Carregando...</div>
      <div v-else-if="!vendas.length" class="p-10 text-center text-sm text-[var(--text-muted)]">Nenhuma venda encontrada.</div>
      <div v-else class="overflow-auto">
        <table class="table">
          <thead><tr><th>Venda</th><th>Data</th><th>Cliente</th><th>NFC-e</th><th class="text-right">Total</th><th class="text-right">Ações</th></tr></thead>
          <tbody>
            <tr v-for="v in vendas" :key="v.id">
              <td><strong>#{{ v.numero }}</strong><span class="block text-[10px] text-[var(--text-muted)]">Pedido {{ v.pedido?.numero || v.pedido_id }}</span></td>
              <td>{{ dataHora(v.concluida_em) }}</td>
              <td>{{ v.cliente?.nome_fantasia || v.cliente?.razao_social || 'Consumidor final' }}</td>
              <td><span :class="['badge', v.nfce_status === 'nao_emitida' ? 'badge-gray' : 'badge-warning']">{{ labelNfce(v.nfce_status) }}</span></td>
              <td class="text-right font-semibold">{{ moeda(v.total) }}</td>
              <td class="text-right">
                <div class="inline-flex gap-2">
                  <button class="btn-secondary btn-sm" @click="abrirComprovante(v.id)">Comprovante</button>
                  <RouterLink :to="`/pdv/fiscal?venda=${v.id}`" class="btn-secondary btn-sm">NFC-e</RouterLink>
                  <RouterLink v-if="v.nfce_status === 'autorizada' || v.nfce_status === 'emitida'" :to="`/pdv/devolucoes?venda=${v.id}`" class="btn-secondary btn-sm">Devolução</RouterLink>
                  <button
                    v-if="v.nfce_status === 'autorizada' || v.nfce_status === 'emitida'"
                    class="btn-danger btn-sm"
                    @click="cancelarNota(v)"
                  >Cancelar nota</button>
                  <button
                    v-if="v.status !== 'cancelada'"
                    class="btn-danger btn-sm"
                    @click="cancelarVenda(v)"
                  >Cancelar venda</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="meta.last_page > 1" class="flex items-center justify-between border-t border-[var(--border)] p-4">
        <button class="btn-secondary btn-sm" :disabled="meta.current_page <= 1" @click="carregar(meta.current_page-1)">Anterior</button>
        <span class="text-xs text-[var(--text-muted)]">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <button class="btn-secondary btn-sm" :disabled="meta.current_page >= meta.last_page" @click="carregar(meta.current_page+1)">Próxima</button>
      </div>
    </div>

    <div v-if="modal" class="modal-backdrop">
      <div class="receipt-modal">
        <div class="receipt-actions no-print">
          <button class="btn-secondary" @click="modal=false">Fechar</button>
          <button class="btn-primary" @click="imprimir">Imprimir</button>
        </div>
        <div id="pdv-receipt" class="receipt" v-if="comprovante">
          <div class="center">
            <strong>{{ comprovante.empresa.nome_fantasia || comprovante.empresa.razao_social }}</strong>
            <span>{{ comprovante.empresa.razao_social }}</span>
            <span v-if="comprovante.empresa.cnpj">CNPJ: {{ comprovante.empresa.cnpj }}</span>
          </div>
          <div class="line"/>
          <div class="row"><span>Venda</span><strong>#{{ comprovante.numero }}</strong></div>
          <div class="row"><span>Data</span><span>{{ comprovante.data }}</span></div>
          <div class="row"><span>Cliente</span><span>{{ comprovante.cliente.nome }}</span></div>
          <div class="line"/>
          <article v-for="(item,i) in comprovante.itens" :key="i" class="item">
            <strong>{{ item.descricao }}</strong>
            <div class="row"><span>{{ numero(item.quantidade,3) }} {{ item.unidade }} × {{ moeda(item.preco_unitario) }}</span><strong>{{ moeda(item.total) }}</strong></div>
          </article>
          <div class="line"/>
          <div class="row"><span>Subtotal</span><strong>{{ moeda(comprovante.subtotal) }}</strong></div>
          <div v-if="comprovante.desconto" class="row"><span>Desconto</span><strong>- {{ moeda(comprovante.desconto) }}</strong></div>
          <div class="row total"><span>Total</span><strong>{{ moeda(comprovante.total) }}</strong></div>
          <div class="line"/>
          <div v-for="(p,i) in comprovante.pagamentos" :key="i" class="row"><span>{{ forma(p.tipo) }}</span><strong>{{ moeda(p.valor) }}</strong></div>
          <div v-if="comprovante.troco" class="row"><span>Troco</span><strong>{{ moeda(comprovante.troco) }}</strong></div>
          <div class="line"/>
          <div class="center"><span>Comprovante não fiscal</span><span>{{ comprovante.codigo }}</span><strong>Obrigado pela preferência!</strong></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const toast=useToast(), loading=ref(false), vendas=ref([]), meta=ref({current_page:1,last_page:1})
const filtros=ref({busca:'',data_inicio:'',data_fim:''}), modal=ref(false), comprovante=ref(null)
const moeda=v=>new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(Number(v||0))
const numero=(v,c=2)=>new Intl.NumberFormat('pt-BR',{minimumFractionDigits:c,maximumFractionDigits:c}).format(Number(v||0))
const dataHora=v=>v?new Date(v).toLocaleString('pt-BR'):'—'
const forma=v=>({dinheiro:'Dinheiro',pix:'PIX',cartao_debito:'Débito',cartao_credito:'Crédito',transferencia:'Transferência',crediario:'Crediário',vale:'Vale',outro:'Outro'}[v]||v)
const labelNfce=v=>({nao_emitida:'Não emitida',preparada:'Preparada',processando:'Processando',rascunho:'Preparada',autorizada:'Autorizada',emitida:'Autorizada',rejeitada:'Rejeitada',cancelada:'Cancelada'}[v]||v)

async function carregar(page=1){
  loading.value=true
  try{
    const {data}=await api.get('/pdv/vendas',{params:{...filtros.value,page}})
    vendas.value=data.data||[];meta.value={current_page:data.current_page||1,last_page:data.last_page||1}
  }finally{loading.value=false}
}
async function abrirComprovante(id){
  const {data}=await api.get(`/pdv/vendas/${id}/comprovante`)
  comprovante.value=data.data;modal.value=true
}
async function preparar(v){
  try{
    const {data}=await api.post(`/pdv/vendas/${v.id}/preparar-nfce`)
    v.nfce_status=data.venda.nfce_status;toast.success(data.message)
  }catch(e){toast.error(e.response?.data?.message||'Falha ao preparar NFC-e.')}
}
async function cancelarNota(v){
  const justificativa=window.prompt('Informe a justificativa do cancelamento fiscal (mínimo de 15 caracteres):')
  if(!justificativa)return
  if(justificativa.trim().length<15){toast.error('A justificativa deve ter pelo menos 15 caracteres.');return}
  if(!window.confirm(`Confirmar o cancelamento da nota fiscal da venda #${v.numero} na SEFAZ?`))return
  try{
    const {data}=await api.post(`/pdv/nfce/${v.id}/cancelar`,{justificativa:justificativa.trim()})
    toast.success(data.message)
    await carregar(meta.value.current_page)
  }catch(e){toast.error(e.response?.data?.message||'Não foi possível cancelar a nota fiscal.')}
}
async function cancelarVenda(v){
  const motivo=window.prompt('Informe o motivo do cancelamento da venda (mínimo de 10 caracteres):')
  if(!motivo)return
  if(motivo.trim().length<10){toast.error('O motivo deve ter pelo menos 10 caracteres.');return}
  if(!window.confirm(`Cancelar a venda #${v.numero}? O estoque e o caixa serão estornados.`))return
  try{
    const {data}=await api.post(`/pdv/vendas/${v.id}/cancelar`,{motivo:motivo.trim()})
    toast.success(data.message)
    await carregar(meta.value.current_page)
  }catch(e){toast.error(e.response?.data?.message||'Não foi possível cancelar a venda.')}
}
function imprimir(){window.print()}
onMounted(()=>carregar())
</script>

<style scoped>
.modal-backdrop{position:fixed;inset:0;z-index:100;background:rgba(0,0,0,.72);display:flex;align-items:center;justify-content:center;padding:16px}
.receipt-modal{width:min(460px,100%);max-height:94vh;overflow:auto}
.receipt-actions{display:flex;justify-content:flex-end;gap:8px;margin-bottom:8px}
.receipt{width:80mm;max-width:100%;margin:auto;background:#fff;color:#111;padding:8mm 5mm;font:11px Arial,sans-serif}
.center{display:flex;flex-direction:column;align-items:center;text-align:center;gap:3px}
.line{border-top:1px dashed #444;margin:9px 0}
.row{display:flex;justify-content:space-between;gap:10px;margin:4px 0}
.item{margin:8px 0}.item>strong{display:block;margin-bottom:4px}.total{font-size:15px}
@media print{body *{visibility:hidden!important}#pdv-receipt,#pdv-receipt *{visibility:visible!important}#pdv-receipt{position:absolute;left:0;top:0;width:80mm}.no-print{display:none!important}}
</style>
