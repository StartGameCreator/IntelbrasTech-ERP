<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <button @click="router.push('/fiscal/nfe')" class="text-xs text-[var(--text-muted)] hover:text-[var(--text-primary)] flex items-center gap-1 mb-1">
          <ArrowLeftIcon class="w-3.5 h-3.5" /> Voltar
        </button>
        <h1 class="page-title">
          {{ nfe?.tipo }} nº {{ nfe?.numero ?? '—' }}<span v-if="nfe?.serie" class="text-[var(--text-muted)]"> / série {{ nfe?.serie }}</span>
        </h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ nfe?.chave_formatada || 'Chave de acesso ainda não gerada' }}</p>
      </div>
      <div class="flex items-center gap-2" v-if="nfe">
        <BadgeStatus :status="nfe.status" />
        <span class="badge badge-gray">{{ nfe.ambiente === 1 ? 'Produção' : 'Homologação' }}</span>
      </div>
    </div>

    <div v-if="loading" class="skeleton h-40 rounded-xl" />

    <template v-else-if="nfe">
      <!-- Ações -->
      <div class="card p-4 flex flex-wrap gap-2">
        <button v-if="nfe.status === 'rascunho'" @click="acaoEmitir" class="btn-primary btn-sm" :disabled="processando">
          <PaperAirplaneIcon class="w-4 h-4" /> Emitir NF-e
        </button>
        <button v-if="nfe.pode_cancelar" @click="modalCancelar = true" class="btn-danger btn-sm">
          <XCircleIcon class="w-4 h-4" /> Cancelar
        </button>
        <button v-if="nfe.pode_carta_correcao" @click="modalCarta = true" class="btn-secondary btn-sm">
          <DocumentTextIcon class="w-4 h-4" /> Carta de Correção
        </button>
        <button v-if="nfe.xml" @click="baixarXml" class="btn-secondary btn-sm">
          <CodeBracketIcon class="w-4 h-4" /> Baixar XML
        </button>
        <button v-if="nfe.danfe_url" @click="baixarDanfe" class="btn-secondary btn-sm">
          <DocumentArrowDownIcon class="w-4 h-4" /> Baixar DANFE
        </button>
        <button v-if="nfe.status === 'autorizada'" @click="acaoEnviarEmail" class="btn-ghost btn-sm">
          <EnvelopeIcon class="w-4 h-4" /> Enviar por e-mail
        </button>
      </div>

      <div v-if="nfe.status === 'rejeitada' && nfe.informacoes_fisco" class="card p-4 border border-danger/30 bg-danger/5">
        <p class="text-xs font-medium text-danger mb-1">Rejeitada pela SEFAZ</p>
        <p class="text-sm text-[var(--text-secondary)]">{{ nfe.informacoes_fisco }}</p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Destinatário -->
        <div class="card p-4 lg:col-span-1">
          <h3 class="text-sm font-semibold mb-3">Destinatário</h3>
          <dl class="space-y-2 text-sm">
            <div><dt class="text-xs text-[var(--text-muted)]">Nome / Razão Social</dt><dd>{{ nfe.destinatario_nome || '—' }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">CPF/CNPJ</dt><dd>{{ cpfCnpj(nfe.destinatario_cpf_cnpj) }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">E-mail</dt><dd>{{ nfe.destinatario_email || '—' }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">Endereço</dt>
              <dd>{{ nfe.destinatario_logradouro }}, {{ nfe.destinatario_numero }} — {{ nfe.destinatario_bairro }}<br>
                  {{ nfe.destinatario_cidade }}/{{ nfe.destinatario_uf }} — {{ cep(nfe.destinatario_cep) }}</dd>
            </div>
          </dl>
        </div>

        <!-- Totais -->
        <div class="card p-4 lg:col-span-2">
          <h3 class="text-sm font-semibold mb-3">Totais</h3>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
            <div><dt class="text-xs text-[var(--text-muted)]">Produtos</dt><dd class="font-medium">{{ moeda(nfe.valor_produtos) }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">Frete</dt><dd class="font-medium">{{ moeda(nfe.valor_frete) }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">Desconto</dt><dd class="font-medium">{{ moeda(nfe.valor_desconto) }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">ICMS</dt><dd class="font-medium">{{ moeda(nfe.valor_icms) }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">IPI</dt><dd class="font-medium">{{ moeda(nfe.valor_ipi) }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">PIS/COFINS</dt><dd class="font-medium">{{ moeda((parseFloat(nfe.valor_pis)||0) + (parseFloat(nfe.valor_cofins)||0)) }}</dd></div>
          </div>
          <div class="mt-4 pt-4 border-t border-[var(--border)] flex items-center justify-between">
            <span class="text-sm text-[var(--text-muted)]">Valor Total da Nota</span>
            <span class="text-lg font-semibold">{{ moeda(nfe.valor_total) }}</span>
          </div>
        </div>
      </div>

      <!-- Itens -->
      <div class="table-wrapper">
        <table class="table">
          <thead><tr>
            <th>#</th><th>Descrição</th><th>NCM</th><th>CFOP</th>
            <th class="text-right">Qtd</th><th class="text-right">Vl. Unit.</th><th class="text-right">Vl. Total</th>
          </tr></thead>
          <tbody>
            <tr v-if="!nfe.itens?.length"><td colspan="7"><div class="empty-state"><p class="text-sm text-[var(--text-muted)]">Nenhum item</p></div></td></tr>
            <tr v-else v-for="item in nfe.itens" :key="item.id">
              <td class="text-xs text-[var(--text-muted)]">{{ item.numero_item }}</td>
              <td class="text-sm">{{ item.descricao }}</td>
              <td class="text-xs">{{ item.ncm || '—' }}</td>
              <td class="text-xs">{{ item.cfop || '—' }}</td>
              <td class="text-sm text-right">{{ decimal(item.quantidade) }}</td>
              <td class="text-sm text-right">{{ moeda(item.valor_unitario) }}</td>
              <td class="text-sm text-right font-medium">{{ moeda(item.valor_total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Cartas de Correção -->
      <div v-if="nfe.cartas_correcao?.length" class="card p-4">
        <h3 class="text-sm font-semibold mb-3">Cartas de Correção</h3>
        <div v-for="carta in nfe.cartas_correcao" :key="carta.id" class="py-2 border-b border-[var(--border)] last:border-0">
          <p class="text-xs text-[var(--text-muted)]">Sequência {{ carta.sequencia }} • Protocolo {{ carta.protocolo }}</p>
          <p class="text-sm">{{ carta.descricao }}</p>
        </div>
      </div>
    </template>

    <div v-else class="empty-state">
      <p class="text-sm text-[var(--text-muted)]">NF-e não encontrada</p>
    </div>

    <!-- Modal Cancelar -->
    <Teleport to="body" v-if="modalCancelar">
      <div class="modal-overlay" @click.self="modalCancelar = false">
        <div class="modal-sm animate-slide-up">
          <div class="modal-header">
            <h3 class="text-base font-semibold text-[var(--text-primary)]">Cancelar NF-e</h3>
            <button @click="modalCancelar = false" class="btn-ghost btn-icon"><XMarkIcon class="w-4 h-4" /></button>
          </div>
          <div class="modal-body space-y-2">
            <label class="label">Justificativa (mínimo 15 caracteres)</label>
            <textarea v-model="justificativaCancelar" rows="3" class="input" placeholder="Descreva o motivo do cancelamento..." />
          </div>
          <div class="modal-footer">
            <button @click="modalCancelar = false" class="btn-secondary">Voltar</button>
            <button @click="acaoCancelar" class="btn-danger" :disabled="justificativaCancelar.length < 15 || processando">Confirmar Cancelamento</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Modal Carta de Correção -->
    <Teleport to="body" v-if="modalCarta">
      <div class="modal-overlay" @click.self="modalCarta = false">
        <div class="modal-sm animate-slide-up">
          <div class="modal-header">
            <h3 class="text-base font-semibold text-[var(--text-primary)]">Carta de Correção</h3>
            <button @click="modalCarta = false" class="btn-ghost btn-icon"><XMarkIcon class="w-4 h-4" /></button>
          </div>
          <div class="modal-body space-y-2">
            <label class="label">Descrição da correção (mínimo 15 caracteres)</label>
            <textarea v-model="descricaoCarta" rows="3" class="input" placeholder="Ex: Correção do endereço de entrega..." />
          </div>
          <div class="modal-footer">
            <button @click="modalCarta = false" class="btn-secondary">Voltar</button>
            <button @click="acaoCartaCorrecao" class="btn-primary" :disabled="descricaoCarta.length < 15 || processando">Emitir Carta</button>
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
import BadgeStatus from '@/components/ui/BadgeStatus.vue'
import { useFormato } from '@/composables/useFormato'
import {
  ArrowLeftIcon, PaperAirplaneIcon, XCircleIcon, DocumentTextIcon,
  CodeBracketIcon, DocumentArrowDownIcon, EnvelopeIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { moeda, data, decimal, cpfCnpj, cep } = useFormato()

const nfe = ref(null)
const loading = ref(false)
const processando = ref(false)
const modalCancelar = ref(false)
const modalCarta = ref(false)
const justificativaCancelar = ref('')
const descricaoCarta = ref('')

async function carregar() {
  loading.value = true
  try {
    const { data: resp } = await api.get(`/nfe/${route.params.id}`)
    nfe.value = resp
  } catch {
    nfe.value = null
  } finally {
    loading.value = false
  }
}

async function acaoEmitir() {
  processando.value = true
  try {
    const { data: resp } = await api.post(`/nfe/${nfe.value.id}/emitir`)
    toast.success(resp.message)
    await carregar()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível emitir a NF-e.')
  } finally {
    processando.value = false
  }
}

async function acaoCancelar() {
  processando.value = true
  try {
    const { data: resp } = await api.post(`/nfe/${nfe.value.id}/cancelar`, { justificativa: justificativaCancelar.value })
    toast.success(resp.message)
    modalCancelar.value = false
    justificativaCancelar.value = ''
    await carregar()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível cancelar a NF-e.')
  } finally {
    processando.value = false
  }
}

async function acaoCartaCorrecao() {
  processando.value = true
  try {
    const { data: resp } = await api.post(`/nfe/${nfe.value.id}/carta-correcao`, { descricao: descricaoCarta.value })
    toast.success(resp.message)
    modalCarta.value = false
    descricaoCarta.value = ''
    await carregar()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível emitir a carta de correção.')
  } finally {
    processando.value = false
  }
}

async function acaoEnviarEmail() {
  try {
    const { data: resp } = await api.post(`/nfe/${nfe.value.id}/enviar-email`)
    toast.success(resp.message)
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível enviar o e-mail.')
  }
}

async function baixarXml() {
  try {
    const { data: resp } = await api.get(`/nfe/${nfe.value.id}/xml`)
    const blob = new Blob([resp.xml], { type: 'application/xml' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = resp.filename || `NFe-${nfe.value.id}.xml`
    link.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    toast.error(e.response?.data?.message || 'XML indisponível.')
  }
}

async function baixarDanfe() {
  try {
    const { data: resp } = await api.get(`/nfe/${nfe.value.id}/danfe`)
    window.open(resp.url, '_blank')
  } catch (e) {
    toast.error(e.response?.data?.message || 'DANFE indisponível.')
  }
}

onMounted(carregar)
</script>
