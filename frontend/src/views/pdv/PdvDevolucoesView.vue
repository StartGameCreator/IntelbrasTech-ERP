<template>
  <div class="page-container space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-semibold">Devoluções e pós-venda</h1>
        <p class="text-sm text-slate-400">Direito de arrependimento, retorno ao estoque, reembolso e NF-e de devolução.</p>
      </div>
      <button class="btn-primary" @click="abrirNova">Nova devolução</button>
    </div>

    <div v-if="formAberto" class="card p-4 space-y-4">
      <h2 class="font-semibold">Registrar devolução</h2>
      <div class="grid md:grid-cols-3 gap-3">
        <label class="space-y-1">
          <span class="text-xs">Venda do PDV</span>
          <input v-model="vendaId" class="input" type="number" placeholder="ID da venda">
        </label>
        <label class="space-y-1">
          <span class="text-xs">Motivo</span>
          <select v-model="form.motivo" class="input">
            <option value="direito_arrependimento">Direito de arrependimento</option>
            <option value="defeito">Produto com defeito</option>
            <option value="avaria">Produto avariado</option>
            <option value="pedido_incorreto">Pedido incorreto</option>
            <option value="desistencia">Desistência do cliente</option>
            <option value="outros">Outros</option>
          </select>
        </label>
        <label class="space-y-1">
          <span class="text-xs">Forma de reembolso</span>
          <select v-model="form.forma_reembolso" class="input">
            <option value="pix">PIX</option>
            <option value="cartao">Estorno no cartão</option>
            <option value="dinheiro">Dinheiro</option>
            <option value="credito_loja">Crédito na loja</option>
            <option value="transferencia">Transferência</option>
          </select>
        </label>
      </div>

      <div class="flex gap-3 items-end">
        <label class="flex-1 space-y-1">
          <span class="text-xs">Descrição</span>
          <input v-model="form.descricao_motivo" class="input" placeholder="Detalhe o motivo">
        </label>
        <button class="btn-secondary" @click="carregarVenda">Carregar venda</button>
      </div>

      <label class="flex items-center gap-2">
        <input v-model="form.mercadoria_retornou" type="checkbox">
        <span>A mercadoria já retornou ao estabelecimento</span>
      </label>

      <div v-if="venda" class="space-y-2">
        <div class="rounded border border-slate-700 p-3">
          <strong>Venda #{{ venda.numero }}</strong>
          <div class="text-sm text-slate-400">Nota: {{ venda.nota_fiscal?.numero }} · {{ venda.nota_fiscal?.status }}</div>
        </div>

        <table class="w-full text-sm">
          <thead><tr><th>Selecionar</th><th>Produto</th><th>Vendida</th><th>Devolver</th><th>Condição</th></tr></thead>
          <tbody>
            <tr v-for="item in venda.pedido?.itens || []" :key="item.id">
              <td><input v-model="selecionados[item.id].ativo" type="checkbox"></td>
              <td>{{ item.descricao }}</td>
              <td>{{ item.quantidade }}</td>
              <td><input v-model.number="selecionados[item.id].quantidade" class="input w-28" type="number" min="0.0001" :max="item.quantidade" step="0.0001"></td>
              <td>
                <select v-model="selecionados[item.id].condicao" class="input">
                  <option value="revendavel">Revendável</option>
                  <option value="avariado">Avariado</option>
                  <option value="defeituoso">Defeituoso</option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>

        <div class="flex justify-end gap-2">
          <button class="btn-secondary" @click="formAberto=false">Cancelar</button>
          <button class="btn-primary" :disabled="salvando" @click="salvar">Registrar devolução</button>
        </div>
      </div>
    </div>

    <div class="card overflow-x-auto">
      <table class="w-full text-sm">
        <thead><tr><th>Processo</th><th>Venda</th><th>Motivo</th><th>Status</th><th>Mercadoria</th><th>Reembolso</th><th>Valor</th><th>Ações</th></tr></thead>
        <tbody>
          <tr v-for="d in devolucoes" :key="d.id">
            <td>{{ d.numero }}</td>
            <td>#{{ d.venda?.numero }}</td>
            <td>{{ d.motivo }}</td>
            <td>{{ d.status }}</td>
            <td>{{ d.situacao_mercadoria }}</td>
            <td>{{ d.status_reembolso }}</td>
            <td>R$ {{ moeda(d.valor_reembolso) }}</td>
            <td class="flex flex-wrap gap-1">
              <button v-if="d.situacao_mercadoria !== 'retornada'" class="btn-secondary btn-sm" @click="receber(d)">Receber</button>
              <button v-if="d.status_reembolso !== 'concluido'" class="btn-secondary btn-sm" @click="reembolsar(d)">Reembolsar</button>
              <button v-if="d.situacao_mercadoria === 'retornada' && !d.nota_fiscal_devolucao_id" class="btn-secondary btn-sm" @click="preparar(d)">Preparar NF-e</button>
              <button v-if="d.nota_fiscal_devolucao?.status === 'rascunho'" class="btn-danger btn-sm" @click="emitir(d)">Emitir NF-e</button>
            </td>
          </tr>
          <tr v-if="!devolucoes.length"><td colspan="8" class="text-center py-8 text-slate-500">Nenhuma devolução registrada.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { useToast } from 'vue-toastification'

const route=useRoute()
const toast=useToast()
const devolucoes=ref([])
const formAberto=ref(false)
const vendaId=ref(route.query.venda || '')
const venda=ref(null)
const selecionados=reactive({})
const salvando=ref(false)
const form=reactive({motivo:'direito_arrependimento',descricao_motivo:'',mercadoria_retornou:false,forma_reembolso:'pix'})

const moeda=v=>Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2})
async function listar(){const {data}=await api.get('/pdv/devolucoes');devolucoes.value=data.data||data}
function abrirNova(){formAberto.value=true;if(vendaId.value)carregarVenda()}
async function carregarVenda(){
  if(!vendaId.value){toast.error('Informe o ID da venda.');return}
  try{
    const {data}=await api.get(`/pdv/devolucoes/venda/${vendaId.value}`)
    venda.value=data
    Object.keys(selecionados).forEach(k=>delete selecionados[k])
    for(const item of data.pedido?.itens||[]) selecionados[item.id]={ativo:true,quantidade:Number(item.quantidade),condicao:'revendavel'}
  }catch(e){toast.error(e.response?.data?.message||'Venda não encontrada.')}
}
async function salvar(){
  const itens=Object.entries(selecionados).filter(([,v])=>v.ativo).map(([id,v])=>({pedido_item_id:Number(id),quantidade:Number(v.quantidade),condicao:v.condicao}))
  if(!itens.length){toast.error('Selecione pelo menos um item.');return}
  salvando.value=true
  try{
    const {data}=await api.post('/pdv/devolucoes',{pdv_venda_id:Number(vendaId.value),...form,itens})
    toast.success(data.message);formAberto.value=false;venda.value=null;await listar()
  }catch(e){toast.error(e.response?.data?.message||'Falha ao registrar devolução.')}
  finally{salvando.value=false}
}
async function action(url,msg){if(!confirm(msg))return;try{const {data}=await api.post(url);toast.success(data.message);await listar()}catch(e){toast.error(e.response?.data?.message||'Operação não concluída.')}}
const receber=d=>action(`/pdv/devolucoes/${d.id}/receber`,'Confirmar que a mercadoria retornou?')
const reembolsar=d=>action(`/pdv/devolucoes/${d.id}/reembolsar`,'Registrar o reembolso financeiro?')
const preparar=d=>action(`/pdv/devolucoes/${d.id}/preparar-nfe`,'Preparar rascunho da NF-e de devolução?')
const emitir=d=>action(`/pdv/devolucoes/${d.id}/emitir-nfe`,'Transmitir a NF-e de devolução para a SEFAZ? Revise os dados fiscais antes de confirmar.')

onMounted(async()=>{await listar();if(vendaId.value)abrirNova()})
</script>
