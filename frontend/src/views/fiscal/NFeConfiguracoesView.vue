<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <div class="flex items-center gap-2 text-xs text-[var(--text-muted)] mb-1">
          <router-link to="/fiscal/nfe" class="hover:text-[var(--text-primary)]">Notas Fiscais</router-link>
          <ChevronRightIcon class="w-3 h-3" />
          <span>Configurações fiscais</span>
        </div>
        <h1 class="page-title">Configurações de NF-e e NFC-e</h1>
        <p class="text-xs text-[var(--text-muted)] mt-1">Defina ambiente, séries e o CSC usado no QR Code da NFC-e.</p>
      </div>
      <router-link to="/fiscal/nfe" class="btn-secondary btn-sm">
        <ArrowLeftIcon class="w-4 h-4" /> Voltar às notas
      </router-link>
    </div>

    <div v-if="loading" class="card p-6"><div class="skeleton h-44 rounded" /></div>

    <template v-else>
      <div class="grid grid-cols-1 xl:grid-cols-[260px_minmax(0,1fr)] gap-5">
        <aside class="card p-2 h-fit">
          <button v-for="item in abas" :key="item.id" @click="aba = item.id"
            class="w-full flex items-center gap-3 px-3 py-3 rounded-lg text-sm transition-colors"
            :class="aba === item.id ? 'bg-[var(--primary-alpha)] text-[var(--primary)]' : 'text-[var(--text-secondary)] hover:bg-[var(--bg-hover)]'">
            <component :is="item.icon" class="w-5 h-5" />
            <span class="font-medium">{{ item.label }}</span>
          </button>
          <div class="border-t border-[var(--border)] mt-2 pt-3 px-3 pb-2 text-xs text-[var(--text-muted)]">
            <p>Layout fiscal: 4.00</p>
            <p class="mt-1">Certificado ativo: <strong :class="certificadoAtivo ? 'text-emerald-400' : 'text-amber-400'">{{ certificadoAtivo ? 'Sim' : 'Não' }}</strong></p>
          </div>
        </aside>

        <main class="space-y-5">
          <section v-if="aba === 'emissao'" class="card overflow-hidden">
            <div class="p-5 border-b border-[var(--border)]">
              <h2 class="text-base font-semibold text-[var(--text-primary)]">Configurações de emissão</h2>
              <p class="text-xs text-[var(--text-muted)] mt-1">Use homologação para testes sem valor fiscal e produção para documentos válidos.</p>
            </div>

            <div class="m-5 rounded-xl border border-sky-500/25 bg-sky-500/10 p-4 flex gap-3">
              <InformationCircleIcon class="w-6 h-6 text-sky-400 shrink-0" />
              <div class="text-sm text-[var(--text-secondary)]">
                <p class="font-semibold text-[var(--text-primary)]">Escolha o ambiente com atenção</p>
                <p>Produção gera documentos com valor fiscal. Homologação é destinada a testes.</p>
              </div>
            </div>

            <form @submit.prevent="salvarConfiguracao" class="p-5 pt-0 space-y-5">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="label">Versão do layout</label>
                  <select v-model="form.versao_layout" class="input" disabled><option value="4.00">4.00</option></select>
                </div>
                <div>
                  <label class="label">UF do emitente</label>
                  <select v-model="form.uf" class="input">
                    <option value="">Selecione</option>
                    <option v-for="uf in ufs" :key="uf" :value="uf">{{ uf }}</option>
                  </select>
                </div>
                <div>
                  <label class="label">Ambiente da NF-e</label>
                  <select v-model.number="form.ambiente_nfe" class="input">
                    <option :value="2">2 - Homologação</option>
                    <option :value="1">1 - Produção</option>
                  </select>
                </div>
                <div>
                  <label class="label">Ambiente da NFC-e</label>
                  <select v-model.number="form.ambiente_nfce" class="input">
                    <option :value="2">2 - Homologação</option>
                    <option :value="1">1 - Produção</option>
                  </select>
                </div>
                <div>
                  <label class="label">Série padrão NF-e</label>
                  <input v-model="form.serie_nfe" class="input" maxlength="5" />
                </div>
                <div>
                  <label class="label">Série padrão NFC-e</label>
                  <input v-model="form.serie_nfce" class="input" maxlength="5" />
                </div>
              </div>

              <div class="rounded-xl border border-[var(--border)] bg-[var(--bg-elevated)] p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p class="font-medium text-[var(--text-primary)]">Validação da configuração fiscal</p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Verifica UF, certificado ativo e CSC de produção. Não transmite documento fiscal.</p>
                  </div>
                  <button type="button" @click="testar" class="btn-secondary" :disabled="testando">
                    <ArrowPathIcon class="w-4 h-4" :class="testando && 'animate-spin'" />
                    {{ testando ? 'Validando...' : 'Testar configuração' }}
                  </button>
                </div>
                <div v-if="teste" class="mt-4 text-sm rounded-lg p-3" :class="teste.ok ? 'bg-emerald-500/10 text-emerald-300' : 'bg-amber-500/10 text-amber-300'">
                  <p class="font-medium">{{ teste.message }}</p>
                  <ul v-if="teste.problemas?.length" class="mt-2 list-disc pl-5 space-y-1"><li v-for="p in teste.problemas" :key="p">{{ p }}</li></ul>
                </div>
              </div>

              <div class="flex justify-end gap-2 border-t border-[var(--border)] pt-5">
                <router-link to="/fiscal/nfe" class="btn-secondary">Cancelar</router-link>
                <button class="btn-primary" :disabled="salvando">
                  <CheckIcon class="w-4 h-4" /> {{ salvando ? 'Salvando...' : 'Salvar configurações' }}
                </button>
              </div>
            </form>
          </section>

          <section v-else class="card overflow-hidden">
            <div class="p-5 border-b border-[var(--border)] flex flex-wrap items-center justify-between gap-3">
              <div>
                <h2 class="text-base font-semibold text-[var(--text-primary)]">CSC da NFC-e</h2>
                <p class="text-xs text-[var(--text-muted)] mt-1">Cadastre separadamente o CSC de homologação e de produção.</p>
              </div>
              <button @click="abrirNovoCsc" class="btn-primary btn-sm"><PlusIcon class="w-4 h-4" /> Novo CSC</button>
            </div>

            <div class="p-5">
              <div class="rounded-xl border border-amber-500/25 bg-amber-500/10 p-4 flex gap-3 mb-5">
                <ShieldCheckIcon class="w-6 h-6 text-amber-400 shrink-0" />
                <div class="text-sm text-[var(--text-secondary)]">
                  <p class="font-semibold text-[var(--text-primary)]">Código de segurança protegido</p>
                  <p>O token é criptografado no banco. Depois de salvo, apenas uma versão mascarada é exibida.</p>
                </div>
              </div>

              <div v-if="!cscs.length" class="empty-state py-12"><p class="text-sm text-[var(--text-muted)]">Nenhum CSC cadastrado.</p></div>
              <div v-else class="space-y-3">
                <div v-for="csc in cscs" :key="csc.id" class="rounded-xl border border-[var(--border)] bg-[var(--bg-elevated)] p-4 flex flex-wrap items-center justify-between gap-4">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" :class="csc.ambiente === 1 ? 'bg-emerald-500/15 text-emerald-400' : 'bg-sky-500/15 text-sky-400'">
                      <KeyIcon class="w-5 h-5" />
                    </div>
                    <div>
                      <p class="font-medium text-[var(--text-primary)]">ID {{ csc.identificador }} · {{ csc.ambiente_nome }}</p>
                      <p class="text-xs text-[var(--text-muted)] font-mono mt-1">{{ csc.codigo_mascarado }}</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="badge" :class="csc.ativo ? 'badge-success' : 'badge-neutral'">{{ csc.ativo ? 'Ativo' : 'Inativo' }}</span>
                    <button @click="excluirCsc(csc)" class="btn-ghost btn-icon text-red-400" title="Excluir"><TrashIcon class="w-4 h-4" /></button>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </main>
      </div>
    </template>

    <div v-if="modalCsc" class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4" @click.self="modalCsc = false">
      <div class="card w-full max-w-lg p-5">
        <div class="flex items-center justify-between mb-5">
          <div><h3 class="text-base font-semibold">Cadastrar CSC</h3><p class="text-xs text-[var(--text-muted)] mt-1">Informe os dados fornecidos pela SEFAZ da sua UF.</p></div>
          <button @click="modalCsc = false" class="btn-ghost btn-icon"><XMarkIcon class="w-5 h-5" /></button>
        </div>
        <form @submit.prevent="salvarCsc" class="space-y-4">
          <div>
            <label class="label">Ambiente</label>
            <select v-model.number="cscForm.ambiente" class="input"><option :value="2">Homologação</option><option :value="1">Produção</option></select>
          </div>
          <div>
            <label class="label">Identificador do CSC</label>
            <input v-model="cscForm.identificador" class="input" maxlength="10" placeholder="Ex.: 1" required />
          </div>
          <div>
            <label class="label">Código CSC (token)</label>
            <input v-model="cscForm.codigo" type="password" class="input" autocomplete="new-password" placeholder="Cole o token fornecido pela SEFAZ" required />
          </div>
          <label class="flex items-center gap-2 text-sm text-[var(--text-secondary)]"><input v-model="cscForm.ativo" type="checkbox" /> CSC ativo</label>
          <div class="flex justify-end gap-2 pt-3 border-t border-[var(--border)]">
            <button type="button" @click="modalCsc = false" class="btn-secondary">Cancelar</button>
            <button class="btn-primary" :disabled="salvandoCsc">{{ salvandoCsc ? 'Salvando...' : 'Salvar CSC' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '@/services/api'
import { useToast } from 'vue-toastification'
import {
  ArrowLeftIcon, ArrowPathIcon, CheckIcon, ChevronRightIcon, Cog6ToothIcon,
  InformationCircleIcon, KeyIcon, PlusIcon, ShieldCheckIcon, TrashIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'

const toast = useToast()
const aba = ref('emissao')
const abas = [
  { id: 'emissao', label: 'Ambientes e emissão', icon: Cog6ToothIcon },
  { id: 'csc', label: 'CSC da NFC-e', icon: KeyIcon },
]
const ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO']
const loading = ref(true)
const salvando = ref(false)
const testando = ref(false)
const teste = ref(null)
const certificadoAtivo = ref(false)
const cscs = ref([])
const modalCsc = ref(false)
const salvandoCsc = ref(false)
const form = reactive({ versao_layout: '4.00', uf: '', ambiente_nfe: 2, ambiente_nfce: 2, serie_nfe: '1', serie_nfce: '1', ativo: true })
const cscForm = reactive({ ambiente: 2, identificador: '', codigo: '', ativo: true })

async function carregar() {
  loading.value = true
  try {
    const { data } = await api.get('/fiscal/configuracoes')
    Object.assign(form, data.configuracao || {})
    cscs.value = data.cscs || []
    certificadoAtivo.value = !!data.certificado_ativo
  } catch (e) { toast.error(e.response?.data?.message || 'Não foi possível carregar as configurações fiscais.') }
  finally { loading.value = false }
}
async function salvarConfiguracao() {
  salvando.value = true
  try { const { data } = await api.put('/fiscal/configuracoes', form); toast.success(data.message || 'Configurações salvas.') }
  catch (e) { toast.error(e.response?.data?.message || 'Não foi possível salvar.') }
  finally { salvando.value = false }
}
async function testar() {
  testando.value = true; teste.value = null
  try { const { data } = await api.post('/fiscal/configuracoes/testar'); teste.value = data; data.ok ? toast.success(data.message) : toast.warning(data.message) }
  catch (e) { toast.error(e.response?.data?.message || 'Falha ao validar configuração.') }
  finally { testando.value = false }
}
function abrirNovoCsc() { Object.assign(cscForm, { ambiente: form.ambiente_nfce || 2, identificador: '', codigo: '', ativo: true }); modalCsc.value = true }
async function salvarCsc() {
  salvandoCsc.value = true
  try { const { data } = await api.post('/fiscal/csc', cscForm); toast.success(data.message); modalCsc.value = false; await carregar(); aba.value = 'csc' }
  catch (e) { toast.error(e.response?.data?.message || 'Não foi possível salvar o CSC.') }
  finally { salvandoCsc.value = false }
}
async function excluirCsc(csc) {
  if (!confirm(`Excluir o CSC ${csc.identificador} de ${csc.ambiente_nome}?`)) return
  try { const { data } = await api.delete(`/fiscal/csc/${csc.id}`); toast.success(data.message); await carregar(); aba.value = 'csc' }
  catch (e) { toast.error(e.response?.data?.message || 'Não foi possível excluir o CSC.') }
}
onMounted(carregar)
</script>
