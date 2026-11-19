<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <button @click="router.push('/os')" class="text-xs text-[var(--text-muted)] hover:text-[var(--text-primary)] flex items-center gap-1 mb-1">
          <ArrowLeftIcon class="w-3.5 h-3.5" /> Voltar para o Kanban
        </button>
        <h1 class="page-title">{{ os?.numero }}</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ os?.cliente?.razao_social || os?.cliente?.nome_fantasia }}</p>
      </div>
      <div class="flex items-center gap-2" v-if="os">
        <select v-model="novoStatusId" @change="mudarStatus" class="select w-52">
          <option v-for="s in statusList" :key="s.id" :value="s.id">{{ s.nome }}</option>
        </select>
        <button @click="gerarPdf" class="btn-secondary btn-sm" :disabled="gerandoPdf">
          <DocumentArrowDownIcon class="w-4 h-4" /> PDF da OS
        </button>
        <router-link :to="`/os/${os.id}/editar`" class="btn-primary btn-sm">
          <PencilIcon class="w-4 h-4" /> Editar
        </router-link>
      </div>
    </div>

    <div v-if="loading" class="skeleton h-40 rounded-xl" />

    <template v-else-if="os">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="card p-4 lg:col-span-1">
          <h3 class="text-sm font-semibold mb-3">Equipamento</h3>
          <dl class="space-y-2 text-sm">
            <div><dt class="text-xs text-[var(--text-muted)]">Tipo</dt><dd>{{ os.equipamento_tipo || '—' }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">Marca / Modelo</dt><dd>{{ os.equipamento_marca }} {{ os.equipamento_modelo }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">Número de Série</dt><dd>{{ os.equipamento_serie || '—' }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">Técnico</dt><dd>{{ os.tecnico?.nome || '—' }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">Em Garantia</dt><dd>{{ os.em_garantia ? 'Sim' : 'Não' }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">Entrada</dt><dd>{{ dataHora(os.data_entrada) }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">Previsão</dt><dd>{{ data(os.data_previsao) }}</dd></div>
          </dl>
        </div>

        <div class="card p-4 lg:col-span-2">
          <h3 class="text-sm font-semibold mb-3">Defeito e Diagnóstico</h3>
          <div class="space-y-3 text-sm">
            <div><dt class="text-xs text-[var(--text-muted)] mb-0.5">Defeito Relatado</dt><dd>{{ os.defeito_relatado || '—' }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)] mb-0.5">Defeito Constatado</dt><dd>{{ os.defeito_constatado || '—' }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)] mb-0.5">Laudo Técnico</dt><dd>{{ os.laudo_tecnico || '—' }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)] mb-0.5">Solução Aplicada</dt><dd>{{ os.solucao || '—' }}</dd></div>
          </div>
          <div class="mt-4 pt-4 border-t border-[var(--border)] flex items-center justify-between">
            <span class="text-sm text-[var(--text-muted)]">Serviço {{ moeda(os.valor_servico) }} + Peças {{ moeda(os.total_pecas) }} − Desconto {{ moeda(os.desconto) }}</span>
            <span class="text-lg font-semibold">{{ moeda(os.total_geral) }}</span>
          </div>
        </div>
      </div>

      <!-- Peças -->
      <div class="card p-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-semibold">Peças Utilizadas</h3>
          <button @click="modalPeca = true" class="btn-secondary btn-sm"><PlusIcon class="w-4 h-4" /> Adicionar Peça</button>
        </div>
        <div v-if="!os.pecas?.length" class="empty-state"><p class="text-xs text-[var(--text-muted)]">Nenhuma peça lançada</p></div>
        <table v-else class="table">
          <thead><tr><th>Descrição</th><th class="text-right">Qtd.</th><th class="text-right">Preço Unit.</th><th class="text-right">Total</th></tr></thead>
          <tbody>
            <tr v-for="p in os.pecas" :key="p.id">
              <td class="text-sm">{{ p.descricao || p.produto?.nome }}</td>
              <td class="text-sm text-right">{{ decimal(p.quantidade, 3) }}</td>
              <td class="text-sm text-right">{{ moeda(p.preco_unitario) }}</td>
              <td class="text-sm text-right font-medium">{{ moeda(p.total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Fotos -->
      <div class="card p-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-semibold">Fotos</h3>
          <label class="btn-secondary btn-sm cursor-pointer">
            <CameraIcon class="w-4 h-4" /> Enviar Foto
            <input type="file" accept="image/*" class="hidden" @change="enviarFoto" />
          </label>
        </div>
        <div v-if="!os.fotos?.length" class="empty-state"><p class="text-xs text-[var(--text-muted)]">Nenhuma foto anexada</p></div>
        <div v-else class="grid grid-cols-4 sm:grid-cols-6 gap-2">
          <img v-for="f in os.fotos" :key="f.id" :src="f.url" :alt="f.descricao" class="w-full aspect-square object-cover rounded-lg border border-[var(--border)]" />
        </div>
      </div>

      <!-- Histórico -->
      <div class="card p-4">
        <h3 class="text-sm font-semibold mb-3">Histórico</h3>
        <div v-if="!os.historico?.length" class="empty-state"><p class="text-xs text-[var(--text-muted)]">Sem movimentações</p></div>
        <div v-else class="space-y-3">
          <div v-for="h in os.historico" :key="h.id" class="flex items-start gap-3 text-sm">
            <div class="w-1.5 h-1.5 rounded-full bg-brand-500 mt-1.5 flex-shrink-0" />
            <div>
              <p>{{ h.status_anterior || 'Aberta' }} → <strong>{{ h.status_novo }}</strong></p>
              <p class="text-xs text-[var(--text-muted)]">{{ dataHora(h.created_at) }} • {{ h.usuario?.nome || 'Sistema' }}</p>
              <p v-if="h.observacao" class="text-xs text-[var(--text-secondary)] mt-0.5">{{ h.observacao }}</p>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="empty-state"><p class="text-sm text-[var(--text-muted)]">Ordem de serviço não encontrada</p></div>

    <!-- Modal Adicionar Peça -->
    <Teleport to="body" v-if="modalPeca">
      <div class="modal-overlay" @click.self="modalPeca = false">
        <div class="modal-sm animate-slide-up">
          <div class="modal-header">
            <h3 class="text-base font-semibold text-[var(--text-primary)]">Adicionar Peça</h3>
            <button @click="modalPeca = false" class="btn-ghost btn-icon"><XMarkIcon class="w-4 h-4" /></button>
          </div>
          <div class="modal-body space-y-3">
            <div class="form-group">
              <label class="label">Descrição</label>
              <input v-model="novaPeca.descricao" class="input" placeholder="Ex: Fonte 12V 1A" />
            </div>
            <div class="form-row-2">
              <div class="form-group">
                <label class="label">Quantidade</label>
                <input v-model.number="novaPeca.quantidade" type="number" step="0.001" min="0.001" class="input" />
              </div>
              <div class="form-group">
                <label class="label">Preço Unitário</label>
                <input v-model.number="novaPeca.preco_unitario" type="number" step="0.01" min="0" class="input" />
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="modalPeca = false" class="btn-secondary">Cancelar</button>
            <button @click="adicionarPeca" class="btn-primary" :disabled="processando">Adicionar</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { useFormato } from '@/composables/useFormato'
import { ArrowLeftIcon, PencilIcon, PlusIcon, CameraIcon, DocumentArrowDownIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { moeda, decimal, data, dataHora } = useFormato()

const os = ref(null)
const statusList = ref([])
const novoStatusId = ref(null)
const loading = ref(false)
const processando = ref(false)
const gerandoPdf = ref(false)
const modalPeca = ref(false)
const novaPeca = ref({ descricao: '', quantidade: 1, preco_unitario: 0 })

async function carregar() {
  loading.value = true
  try {
    const { data: resp } = await api.get(`/os/${route.params.id}`)
    os.value = resp
    novoStatusId.value = resp.status_id
  } catch {
    os.value = null
  } finally {
    loading.value = false
  }
}

async function carregarStatus() {
  try {
    const { data } = await api.get('/os-status')
    statusList.value = data
  } catch { /* opcional */ }
}

async function mudarStatus() {
  if (novoStatusId.value === os.value.status_id) return
  processando.value = true
  try {
    const { data } = await api.post(`/os/${os.value.id}/mudar-status`, { status_id: novoStatusId.value })
    toast.success(data.message)
    await carregar()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível mudar o status.')
    novoStatusId.value = os.value.status_id
  } finally {
    processando.value = false
  }
}

async function adicionarPeca() {
  processando.value = true
  try {
    const { data } = await api.post(`/os/${os.value.id}/pecas`, novaPeca.value)
    toast.success(data.message)
    modalPeca.value = false
    novaPeca.value = { descricao: '', quantidade: 1, preco_unitario: 0 }
    await carregar()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível adicionar a peça.')
  } finally {
    processando.value = false
  }
}

async function enviarFoto(evento) {
  const arquivo = evento.target.files[0]
  if (!arquivo) return
  const formData = new FormData()
  formData.append('foto', arquivo)
  try {
    const { data } = await api.post(`/os/${os.value.id}/fotos`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    toast.success(data.message)
    await carregar()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível enviar a foto.')
  }
}

async function gerarPdf() {
  gerandoPdf.value = true
  try {
    const { data } = await api.get(`/os/${os.value.id}/pdf-os`)
    window.open(data.url, '_blank')
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível gerar o PDF.')
  } finally {
    gerandoPdf.value = false
  }
}

onMounted(() => {
  carregar()
  carregarStatus()
})
</script>
