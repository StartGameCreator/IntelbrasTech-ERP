<template>
  <div class="space-y-4">
    <div class="page-header"><div><h1 class="page-title">Central de Notas Fiscais</h1><p class="text-xs text-[var(--text-muted)]">NF-e e NFC-e, segunda via, XML e consulta SEFAZ</p></div><button class="btn-secondary" @click="carregarTudo">Atualizar</button></div>
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
      <div v-for="c in cards" :key="c.label" class="card p-4"><div class="text-xs text-[var(--text-muted)]">{{c.label}}</div><div class="text-xl font-semibold mt-1">{{c.valor}}</div></div>
    </div>
    <div class="card p-3 grid grid-cols-1 md:grid-cols-6 gap-2">
      <input v-model="f.busca" class="input md:col-span-2" placeholder="Cliente, CPF/CNPJ, chave ou número" @keyup.enter="carregar" />
      <select v-model="f.tipo" class="input"><option value="">Todos os tipos</option><option>NFe</option><option>NFCe</option></select>
      <select v-model="f.status" class="input"><option value="">Todos os status</option><option value="autorizada">Autorizada</option><option value="cancelada">Cancelada</option><option value="rascunho">Rascunho</option><option value="rejeitada">Rejeitada</option></select>
      <input v-model="f.inicio" type="date" class="input" />
      <button class="btn-primary" @click="carregar">Pesquisar</button>
    </div>
    <div class="table-wrapper">
      <table class="table"><thead><tr><th>Tipo</th><th>Nº/Série</th><th>Destinatário</th><th>Documento</th><th>Emissão</th><th>Valor</th><th>Status</th><th class="text-right">Ações</th></tr></thead>
      <tbody><tr v-if="loading"><td colspan="8" class="p-8 text-center">Carregando...</td></tr><tr v-else-if="!items.length"><td colspan="8" class="p-8 text-center">Nenhuma nota encontrada.</td></tr>
      <tr v-for="n in items" :key="n.id"><td>{{n.tipo}}</td><td>{{n.numero||'—'}} / {{n.serie||'—'}}</td><td>{{n.destinatario_nome||'Consumidor'}}</td><td>{{n.destinatario_cpf_cnpj||'—'}}</td><td>{{fmtData(n.data_emissao)}}</td><td>{{moeda(n.valor_total)}}</td><td>{{n.status}}</td><td class="text-right"><div class="flex justify-end gap-1"><button class="btn-secondary btn-sm" @click="segundaVia(n)">2ª via</button><button class="btn-secondary btn-sm" @click="baixarXml(n)">XML</button><button class="btn-secondary btn-sm" @click="consultar(n)">SEFAZ</button><router-link class="btn-secondary btn-sm" :to="`/fiscal/nfe/${n.id}`">Abrir</router-link></div></td></tr></tbody></table>
    </div>
    <div v-if="mensagem" class="card p-3 text-sm">{{mensagem}}</div>
  </div>
</template>
<script setup>
import {ref, computed, onMounted} from 'vue'; import api from '@/services/api'
const items=ref([]), dash=ref({}), loading=ref(false), mensagem=ref(''); const f=ref({busca:'',tipo:'',status:'',inicio:''})
const cards=computed(()=>[{label:'Total',valor:dash.value.total||0},{label:'Autorizadas',valor:dash.value.autorizadas||0},{label:'Canceladas',valor:dash.value.canceladas||0},{label:'Pendentes',valor:dash.value.pendentes||0},{label:'Emitidas hoje',valor:dash.value.emitidas_hoje||0},{label:'Valor autorizado',valor:moeda(dash.value.valor_autorizado)}])
function moeda(v){return Number(v||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'})} function fmtData(v){return v?new Date(v).toLocaleString('pt-BR'):'—'}
async function carregar(){loading.value=true;try{const {data}=await api.get('/fiscal/central/notas',{params:{...f.value,per_page:100}});items.value=data.data||data}catch(e){mensagem.value=e?.response?.data?.message||'Erro ao carregar notas.'}finally{loading.value=false}}
async function carregarTudo(){const {data}=await api.get('/fiscal/central/dashboard');dash.value=data;await carregar()}
async function baixar(url,nome){const r=await api.get(url,{responseType:'blob'});const a=document.createElement('a');a.href=URL.createObjectURL(r.data);a.download=nome;a.click();URL.revokeObjectURL(a.href)}
async function segundaVia(n){try{await baixar(`/fiscal/central/notas/${n.id}/segunda-via`,`SEGUNDA-VIA-${n.tipo}-${n.numero}.pdf`)}catch(e){mensagem.value=e?.response?.data?.message||'Não foi possível gerar a segunda via.'}}
async function baixarXml(n){try{await baixar(`/fiscal/central/notas/${n.id}/xml`,`${n.tipo}-${n.chave_acesso||n.id}.xml`)}catch(e){mensagem.value='XML não disponível.'}}
async function consultar(n){try{const {data}=await api.post(`/fiscal/central/notas/${n.id}/consultar-sefaz`);mensagem.value=JSON.stringify(data)}catch(e){mensagem.value=e?.response?.data?.message||'Falha na consulta SEFAZ.'}}
onMounted(carregarTudo)
</script>
