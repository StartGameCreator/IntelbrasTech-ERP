<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Catálogo Mercado Livre</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">
          Vincule produtos do ERP, importe anúncios e sincronize preço e estoque.
        </p>
      </div>
      <div class="flex gap-2">
        <RouterLink to="/marketplaces/mercado-livre" class="btn-secondary">Conta</RouterLink>
        <button class="btn-primary" :disabled="importando" @click="importar">
          {{ importando ? 'Importando...' : 'Importar anúncios' }}
        </button>
      </div>
    </div>

    <div class="card p-4">
      <div class="flex gap-3">
        <input v-model="busca" class="input flex-1" placeholder="Produto, SKU, anúncio ou ID externo"/>
        <button class="btn-secondary" @click="carregar(1)">Pesquisar</button>
      </div>
    </div>

    <div class="card overflow-hidden">
      <div v-if="loading" class="p-10 text-center text-sm text-[var(--text-muted)]">Carregando...</div>
      <div v-else-if="!items.length" class="p-10 text-center text-sm text-[var(--text-muted)]">
        Nenhum produto vinculado. Importe os anúncios ou crie um vínculo manual.
      </div>

      <div v-else class="overflow-auto">
        <table class="table">
          <thead>
            <tr>
              <th>Produto ERP</th>
              <th>Anúncio</th>
              <th>Preço ML</th>
              <th>Estoque ML</th>
              <th>Status</th>
              <th>Última sincronização</th>
              <th class="text-right">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td>
                <strong>{{ item.produto_nome }}</strong>
                <span class="block text-[10px] text-[var(--text-muted)]">{{ item.sku || item.codigo || 'Sem SKU' }}</span>
              </td>
              <td>
                <strong>{{ item.titulo_externo || item.external_id }}</strong>
                <span class="block text-[10px] text-[var(--text-muted)]">{{ item.external_id }}</span>
              </td>
              <td>{{ moeda(item.preco) }}</td>
              <td>{{ numero(item.estoque, 0) }}</td>
              <td>
                <span :class="['badge', item.status === 'erro' ? 'badge-danger' : item.status === 'sincronizado' ? 'badge-success' : 'badge-warning']">
                  {{ item.status }}
                </span>
              </td>
              <td>{{ dataHora(item.sincronizado_em) }}</td>
              <td class="text-right">
                <div class="inline-flex gap-2">
                  <button class="btn-secondary btn-sm" @click="sincronizar(item)">Sincronizar</button>
                  <a v-if="item.permalink" :href="item.permalink" target="_blank" class="btn-ghost btn-sm">Abrir anúncio</a>
                </div>
                <p v-if="item.ultimo_erro" class="mt-1 text-[10px] text-danger">{{ item.ultimo_erro }}</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="meta.last_page > 1" class="flex items-center justify-between border-t border-[var(--border)] p-4">
        <button class="btn-secondary btn-sm" :disabled="meta.current_page<=1" @click="carregar(meta.current_page-1)">Anterior</button>
        <span class="text-xs text-[var(--text-muted)]">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <button class="btn-secondary btn-sm" :disabled="meta.current_page>=meta.last_page" @click="carregar(meta.current_page+1)">Próxima</button>
      </div>
    </div>

    <div class="card p-4">
      <h2 class="text-sm font-semibold">Vínculo manual</h2>
      <div class="mt-3 grid gap-3 md:grid-cols-4">
        <input v-model="vinculo.produto_id" class="input" placeholder="ID do produto ERP"/>
        <input v-model="vinculo.external_id" class="input" placeholder="ID do anúncio ML"/>
        <input v-model="vinculo.titulo_externo" class="input" placeholder="Título do anúncio"/>
        <button class="btn-primary" @click="vincular">Salvar vínculo</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const toast=useToast()
const loading=ref(false),importando=ref(false),items=ref([]),busca=ref('')
const meta=ref({current_page:1,last_page:1})
const vinculo=ref({produto_id:'',external_id:'',titulo_externo:''})

const moeda=v=>new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(Number(v||0))
const numero=(v,c=2)=>new Intl.NumberFormat('pt-BR',{minimumFractionDigits:c,maximumFractionDigits:c}).format(Number(v||0))
const dataHora=v=>v?new Date(v).toLocaleString('pt-BR'):'—'

async function carregar(page=1){
  loading.value=true
  try{
    const {data}=await api.get('/marketplace/mercado-livre/catalogo',{params:{busca:busca.value,page}})
    items.value=data.data||[]
    meta.value={current_page:data.current_page||1,last_page:data.last_page||1}
  }catch(e){toast.error(e.response?.data?.message||'Não foi possível carregar o catálogo.')}
  finally{loading.value=false}
}

async function importar(){
  importando.value=true
  try{
    const {data}=await api.post('/marketplace/mercado-livre/catalogo/importar')
    toast.success(`${data.message} Vinculados: ${data.vinculados}. Sem vínculo: ${data.sem_vinculo}.`)
    await carregar()
  }catch(e){toast.error(e.response?.data?.message||'Falha ao importar anúncios.')}
  finally{importando.value=false}
}

async function sincronizar(item){
  try{
    const {data}=await api.post(`/marketplace/mercado-livre/catalogo/${item.id}/sincronizar`,{
      sincronizar_preco:true,
      sincronizar_estoque:true
    })
    toast.success(data.message)
    await carregar(meta.value.current_page)
  }catch(e){toast.error(e.response?.data?.message||'Falha na sincronização.')}
}

async function vincular(){
  try{
    const {data}=await api.post('/marketplace/mercado-livre/catalogo/vincular',vinculo.value)
    toast.success(data.message)
    vinculo.value={produto_id:'',external_id:'',titulo_externo:''}
    await carregar()
  }catch(e){toast.error(e.response?.data?.message||'Falha ao vincular produto.')}
}

onMounted(()=>carregar())
</script>
