<template>
  <div class="space-y-5">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h1 class="text-xl font-bold text-[var(--text-primary)]">Certificado Digital</h1>
        <p class="text-xs text-[var(--text-muted)] mt-1">Configure e gerencie os certificados usados na emissão fiscal.</p>
      </div>
      <button class="btn-primary btn-sm" @click="abrirCadastro">+ Adicionar certificado</button>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[270px_minmax(0,1fr)] gap-5">
      <aside class="space-y-4">
        <div class="card p-3">
          <button class="w-full flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500/10 px-3 py-2 text-left text-xs font-semibold text-brand-300">
            <ShieldCheckIcon class="w-4 h-4" /> Configurações de certificado digital
          </button>
        </div>

        <div class="card p-4 space-y-4">
          <div>
            <h3 class="text-sm font-semibold">Downloads</h3>
            <a class="mt-2 text-xs text-brand-400 hover:text-brand-300 flex items-center gap-2" href="/downloads/IntelbrasTech-Assinador-Windows-v0.1.0.zip" download>
              <ArrowDownTrayIcon class="w-4 h-4" /> Baixar Assinador para Windows
            </a>
          </div>
          <div>
            <h3 class="text-sm font-semibold">Links úteis</h3>
            <div class="mt-2 space-y-2 text-xs">
              <button class="text-brand-400 hover:text-brand-300 flex items-center gap-2" @click="mostrarAjuda = true">
                <ArrowTopRightOnSquareIcon class="w-4 h-4" /> Como configurar o certificado
              </button>
              <button class="text-brand-400 hover:text-brand-300 flex items-center gap-2" @click="mostrarAjuda = true">
                <PlayCircleIcon class="w-4 h-4" /> Guia de configuração
              </button>
            </div>
          </div>
        </div>
      </aside>

      <section class="space-y-4 min-w-0">
        <div>
          <p class="text-xs font-semibold text-[var(--text-muted)] mb-2">Tipos de certificados</p>
          <div class="flex flex-wrap gap-2">
            <button v-for="tab in tabs" :key="tab.id" @click="aba = tab.id"
              :class="['px-4 py-3 rounded-lg border text-sm font-semibold transition-colors', aba === tab.id ? 'border-brand-500 bg-brand-500/15 text-brand-300' : 'border-[var(--border)] bg-[var(--bg-surface)] text-[var(--text-secondary)] hover:bg-[var(--bg-elevated)]']">
              {{ tab.label }}
            </button>
          </div>
        </div>

        <div class="rounded-xl border border-info/30 bg-info/10 p-4 flex gap-3">
          <InformationCircleIcon class="w-7 h-7 text-info flex-shrink-0" />
          <div>
            <p class="text-sm font-semibold text-[var(--text-primary)]">Indicações de uso</p>
            <p class="text-xs text-[var(--text-secondary)] mt-1">{{ textoAba }}</p>
          </div>
        </div>

        <div v-if="aba === 'dispositivo'" class="card p-5 space-y-4">
          <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"><div><div class="flex items-center gap-2"><h2 class="text-base font-bold">Certificado instalado neste dispositivo</h2><span class="badge badge-warning">Experimental</span></div><p class="text-xs text-[var(--text-secondary)] mt-1">Opção adicional para testes. O certificado A1 ativo no servidor permanece intacto e continua sendo o modo padrão.</p></div><span :class="['badge', conectorOnline ? 'badge-success':'badge-gray']">{{ conectorOnline ? 'Assinador conectado':'Assinador não detectado' }}</span></div>
          <div class="rounded-lg border border-brand-500/30 bg-brand-500/10 p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between"><div><p class="text-sm font-semibold">Assinador independente para Windows</p><p class="text-xs text-[var(--text-secondary)] mt-1">Baixe, extraia e execute o instalador no computador que possui o certificado.</p></div><a class="btn-primary btn-sm whitespace-nowrap" href="/downloads/IntelbrasTech-Assinador-Windows-v0.1.0.zip" download><ArrowDownTrayIcon class="w-4 h-4" /> Baixar para Windows</a></div>
          <div class="rounded-lg border border-[var(--border)] bg-[var(--bg-elevated)] p-4"><label class="space-y-1"><span class="label">Código de pareamento exibido pelo Assinador Windows</span><div class="flex gap-2"><input v-model="pairingToken" type="password" class="input" placeholder="Cole o código local"><button class="btn-primary whitespace-nowrap" @click="conectarAssinador" :disabled="conectando">{{ conectando ? 'Conectando...':'Conectar dispositivo' }}</button></div></label><p class="text-[11px] text-[var(--text-muted)] mt-2">O código fica somente nesta sessão. A chave privada não é enviada ao ERP.</p></div>
          <div v-if="certificadosDispositivo.length" class="space-y-2"><article v-for="cert in certificadosDispositivo" :key="cert.thumbprint" class="rounded-lg border border-[var(--border)] p-4 flex flex-col gap-3 md:flex-row md:items-center"><div class="flex-1"><b class="text-sm">{{ cert.titular }}</b><p class="text-xs text-[var(--text-muted)]">{{ cert.documento || 'Documento não identificado' }} · válido até {{ formatarData(cert.validade) }}</p><p class="text-[10px] text-[var(--text-muted)] break-all">{{ cert.thumbprint }}</p></div><button class="btn-secondary btn-sm" :disabled="!cert.valido" @click="registrarDispositivo(cert)">Usar como opção experimental</button></article></div>
          <div class="rounded-lg border border-warning/30 bg-warning/10 p-3 text-xs text-[var(--text-secondary)]"><b>Importante:</b> nesta fase validamos detecção, metadados e pareamento. A emissão real continuará no certificado do servidor até a homologação da assinatura e transmissão local com a SEFAZ.</div>
        </div>

        <div v-if="carregando" class="card p-16 text-center text-sm text-[var(--text-muted)]">Carregando certificados...</div>

        <template v-else>
          <div v-if="certificadosFiltrados.length" class="space-y-4">
            <article v-for="cert in certificadosFiltrados" :key="cert.id" class="card overflow-hidden">
              <div :class="['p-5 border-l-4', statusClasse(cert).borda]">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center">
                  <div :class="['w-24 h-24 rounded-full flex items-center justify-center flex-shrink-0', statusClasse(cert).fundo]">
                    <LockClosedIcon class="w-12 h-12" :class="statusClasse(cert).icone" />
                  </div>

                  <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                      <h2 class="text-lg font-bold" :class="statusClasse(cert).texto">{{ statusTitulo(cert) }}</h2>
                      <span class="badge" :class="statusBadge(cert)">{{ statusLabel(cert) }}</span>
                      <span v-if="cert.ativo" class="badge badge-success">Ativo</span>
                      <span class="badge">{{ cert.tipo }} - {{ armazenamentoLabel(cert.armazenamento) }}</span>
                    </div>
                    <dl class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-1 text-xs text-[var(--text-secondary)]">
                      <div><dt class="inline font-semibold">Titular:</dt> <dd class="inline">{{ cert.titular || cert.nome || 'Não informado' }}</dd></div>
                      <div><dt class="inline font-semibold">Documento:</dt> <dd class="inline">{{ cert.documento || 'Não identificado' }}</dd></div>
                      <div><dt class="inline font-semibold">Emissor:</dt> <dd class="inline">{{ cert.emissor || 'Não informado' }}</dd></div>
                      <div><dt class="inline font-semibold">Série:</dt> <dd class="inline break-all">{{ cert.serie || 'Não informada' }}</dd></div>
                      <div><dt class="inline font-semibold">Validade inicial:</dt> <dd class="inline">{{ formatarData(cert.validade_inicio) }}</dd></div>
                      <div><dt class="inline font-semibold">Validade final:</dt> <dd class="inline">{{ formatarData(cert.validade) }}</dd></div>
                      <div><dt class="inline font-semibold">Cadastrado no ERP:</dt> <dd class="inline">{{ formatarDataHora(cert.created_at) }}</dd></div>
                    </dl>

                    <div class="mt-4 flex flex-wrap gap-2">
                      <button class="btn-primary btn-sm" @click="editar(cert)">Atualizar certificado</button>
                      <button class="btn-secondary btn-sm" :disabled="!cert.possui_arquivo" @click="exportar(cert)">Exportar certificado</button>
                      <button v-if="!cert.ativo && cert.armazenamento === 'servidor'" class="btn-secondary btn-sm" @click="ativar(cert)">Definir como ativo</button>
                      <button class="btn-danger btn-sm" @click="remover(cert)">Excluir certificado</button>
                    </div>
                  </div>
                </div>
              </div>
            </article>
          </div>

          <div v-else class="card p-12 text-center">
            <ShieldExclamationIcon class="w-12 h-12 mx-auto text-[var(--text-muted)]" />
            <h3 class="mt-3 text-sm font-semibold">Nenhum certificado deste tipo</h3>
            <p class="text-xs text-[var(--text-muted)] mt-1">Adicione um certificado para habilitar a emissão de documentos fiscais.</p>
            <button class="btn-primary btn-sm mt-4" @click="abrirCadastro">Adicionar certificado</button>
          </div>
        </template>

        <div class="rounded-xl border border-info/30 bg-info/10 p-4 flex gap-3">
          <InformationCircleIcon class="w-7 h-7 text-info flex-shrink-0" />
          <div class="text-xs text-[var(--text-secondary)]">
            <p class="text-sm font-semibold text-[var(--text-primary)] mb-1">Atenção</p>
            <ul class="list-disc pl-4 space-y-1">
              <li>Mantenha uma cópia segura do certificado e da senha.</li>
              <li>Somente usuários autorizados podem cadastrar, exportar ou excluir certificados.</li>
              <li>Ao ativar um novo certificado, os anteriores são mantidos, porém ficam inativos.</li>
            </ul>
          </div>
        </div>
      </section>
    </div>

    <div v-if="modalAberto" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="fecharModal">
      <div class="card w-full max-w-2xl max-h-[90vh] overflow-y-auto p-5">
        <div class="flex items-center justify-between mb-5">
          <div>
            <h2 class="text-lg font-bold">{{ editando ? 'Atualizar certificado' : 'Adicionar certificado' }}</h2>
            <p class="text-xs text-[var(--text-muted)] mt-1">Arquivos aceitos: PFX e P12, até 10 MB.</p>
          </div>
          <button class="btn-ghost btn-icon" @click="fecharModal"><XMarkIcon class="w-5 h-5" /></button>
        </div>

        <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="salvar">
          <label class="space-y-1"><span class="label">Tipo</span><select v-model="form.tipo" class="select" required><option>A1</option><option>A3</option></select></label>
          <label class="space-y-1"><span class="label">Armazenamento</span><select v-model="form.armazenamento" class="select"><option value="servidor">Servidor</option><option value="cliente">Máquina do cliente</option><option value="windows">Gerenciador do Windows</option></select></label>
          <label class="space-y-1 md:col-span-2"><span class="label">Arquivo PFX/P12 {{ editando ? '(opcional)' : '' }}</span><input ref="fileInput" type="file" accept=".pfx,.p12" class="input" @change="selecionarArquivo" /></label>
          <label class="space-y-1 md:col-span-2"><span class="label">Senha</span><input v-model="form.senha" type="password" class="input" placeholder="Senha do certificado" @blur="inspecionarCertificado" /></label>
          <div v-if="inspecionando" class="md:col-span-2 rounded-lg border border-info/30 bg-info/10 p-3 text-xs text-[var(--text-secondary)]">Lendo e validando o certificado...</div>
          <div v-if="metadataLida" class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-3 rounded-lg border border-success/30 bg-success/10 p-4">
            <div class="md:col-span-2"><span class="label">Nome identificado automaticamente</span><p class="text-sm font-semibold">{{ metadataLida.titular }}</p></div>
            <div><span class="label">Status</span><p class="text-sm font-semibold" :class="statusClasse(metadataLida).texto">{{ statusLabel(metadataLida) }}</p></div>
            <div><span class="label">Data de criação/emissão</span><p class="text-sm">{{ formatarData(metadataLida.validade_inicio) }}</p></div>
            <div><span class="label">Data de vencimento</span><p class="text-sm">{{ formatarData(metadataLida.validade) }}</p></div>
            <div><span class="label">Documento</span><p class="text-sm">{{ metadataLida.documento || 'Não identificado' }}</p></div>
            <div class="md:col-span-2"><span class="label">Série</span><p class="text-xs break-all">{{ metadataLida.serie }}</p></div>
          </div>
          <label class="space-y-1 md:col-span-2"><span class="label">Observações</span><textarea v-model="form.observacoes" class="input min-h-24"></textarea></label>
          <label class="md:col-span-2 flex items-center gap-2 text-sm"><input v-model="form.ativo" type="checkbox" /> Definir como certificado ativo</label>
          <div class="md:col-span-2 flex justify-end gap-2 pt-2"><button type="button" class="btn-secondary" @click="fecharModal">Cancelar</button><button class="btn-primary" :disabled="salvando">{{ salvando ? 'Salvando...' : 'Salvar' }}</button></div>
        </form>
      </div>
    </div>

    <div v-if="mostrarAjuda || mostrarAjudaWindows" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="mostrarAjuda = mostrarAjudaWindows = false">
      <div class="card max-w-xl p-6">
        <h2 class="text-lg font-bold">{{ mostrarAjudaWindows ? 'Gerenciador do Windows' : 'Como configurar' }}</h2>
        <p class="text-sm text-[var(--text-secondary)] mt-3">{{ mostrarAjudaWindows ? 'O modo Windows será usado para certificados A3 ou A1 mantidos localmente. O conector local será disponibilizado em uma etapa posterior; por enquanto, registre o certificado e selecione o armazenamento correspondente.' : 'Para A1 no servidor, selecione o arquivo PFX/P12, informe a senha e salve. Os metadados são lidos automaticamente quando o OpenSSL está habilitado no PHP.' }}</p>
        <div class="mt-5 text-right"><button class="btn-primary btn-sm" @click="mostrarAjuda = mostrarAjudaWindows = false">Entendi</button></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { ArrowDownTrayIcon, ArrowTopRightOnSquareIcon, InformationCircleIcon, LockClosedIcon, PlayCircleIcon, ShieldCheckIcon, ShieldExclamationIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const toast = useToast()
const aba = ref('a1-servidor')
const carregando = ref(false)
const salvando = ref(false)
const inspecionando = ref(false)
const metadataLida = ref(null)
const certificados = ref([])
const modalAberto = ref(false)
const editando = ref(null)
const arquivo = ref(null)
const fileInput = ref(null)
const mostrarAjuda = ref(false)
const mostrarAjudaWindows = ref(false)
const conectorOnline=ref(false),conectando=ref(false),pairingToken=ref(''),certificadosDispositivo=ref([])

const tabs = [
  { id: 'a1-servidor', label: 'A1 - Servidor' },
  { id: 'a1-cliente', label: 'A1 - Máquina do cliente' },
  { id: 'a3', label: 'A3' },
  { id: 'windows', label: 'Gerenciador do Windows' },
  { id: 'dispositivo', label: 'Dispositivo (experimental)' },
]

const formVazio = () => ({ tipo: 'A1', armazenamento: 'servidor', senha: '', observacoes: '', ativo: true })
const form = ref(formVazio())

const textoAba = computed(() => ({
  'a1-servidor': 'O certificado fica protegido no servidor e pode ser usado para emitir notas em qualquer dispositivo. Extensões aceitas: PFX e P12.',
  'a1-cliente': 'O certificado A1 permanece na máquina do usuário e depende do conector local para assinar documentos.',
  a3: 'Certificados A3 utilizam token ou cartão conectado à máquina do usuário.',
  windows: 'Integração com o repositório de certificados do Windows para emissão local.',
  dispositivo: 'Detecta certificados instalados por meio do IntelbrasTech Assinador Local sem substituir o A1 do servidor.',
}[aba.value]))

const certificadosFiltrados = computed(() => certificados.value.filter(c => {
  if (aba.value === 'a1-servidor') return c.tipo === 'A1' && (c.armazenamento || 'servidor') === 'servidor'
  if (aba.value === 'a1-cliente') return c.tipo === 'A1' && c.armazenamento === 'cliente'
  if (aba.value === 'a3') return c.tipo === 'A3'
  if (aba.value === 'dispositivo') return c.armazenamento === 'windows'
  return c.armazenamento === 'windows'
}))

function abrirCadastro() {
  editando.value = null
  form.value = formVazio()
  if (aba.value === 'a1-cliente') form.value.armazenamento = 'cliente'
  if (aba.value === 'a3') form.value.tipo = 'A3'
  if (aba.value === 'windows') form.value.armazenamento = 'windows'
  arquivo.value = null
  metadataLida.value = null
  modalAberto.value = true
}
function editar(cert) {
  editando.value = cert
  form.value = { tipo: cert.tipo, armazenamento: cert.armazenamento || 'servidor', senha: '', observacoes: cert.observacoes || '', ativo: cert.ativo }
  metadataLida.value = cert
  arquivo.value = null
  modalAberto.value = true
}
function fecharModal() { modalAberto.value = false; arquivo.value = null; if (fileInput.value) fileInput.value.value = '' }
function selecionarArquivo(e) {
  arquivo.value = e.target.files?.[0] || null
  metadataLida.value = null
  if (arquivo.value && form.value.senha) inspecionarCertificado()
}

async function inspecionarCertificado() {
  if (!arquivo.value || !form.value.senha || inspecionando.value) return
  inspecionando.value = true
  metadataLida.value = null
  try {
    const fd = new FormData()
    fd.append('arquivo', arquivo.value)
    fd.append('senha', form.value.senha)
    const { data } = await api.post('/certificados-digitais/inspecionar', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    metadataLida.value = data.data
    toast.success(data.message)
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível identificar o certificado. Confira o arquivo e a senha.')
  } finally { inspecionando.value = false }
}

async function carregar() {
  carregando.value = true
  try { const { data } = await api.get('/certificados-digitais'); certificados.value = data.data || [] }
  catch (e) { toast.error(e.response?.data?.message || 'Não foi possível carregar os certificados.') }
  finally { carregando.value = false }
}

async function salvar() {
  if (!editando.value && !metadataLida.value) {
    await inspecionarCertificado()
    if (!metadataLida.value) return
  }
  salvando.value = true
  try {
    const fd = new FormData()
    Object.entries(form.value).forEach(([k,v]) => { if (v !== '' && v !== null) fd.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : v) })
    if (arquivo.value) fd.append('arquivo', arquivo.value)
    const url = editando.value ? `/certificados-digitais/${editando.value.id}` : '/certificados-digitais'
    if (editando.value) fd.append('_method', 'PUT')
    const { data } = await api.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    toast.success(data.message)
    fecharModal(); await carregar()
  } catch (e) {
    const errors = e.response?.data?.errors
    toast.error(errors ? Object.values(errors).flat()[0] : (e.response?.data?.message || 'Não foi possível salvar o certificado.'))
  } finally { salvando.value = false }
}

async function ativar(cert) { try { const { data } = await api.post(`/certificados-digitais/${cert.id}/ativar`); toast.success(data.message); await carregar() } catch(e) { toast.error(e.response?.data?.message || 'Não foi possível ativar.') } }
async function remover(cert) { if (!confirm('Excluir este certificado? Esta ação não pode ser desfeita.')) return; try { const { data } = await api.delete(`/certificados-digitais/${cert.id}`); toast.success(data.message); await carregar() } catch(e) { toast.error(e.response?.data?.message || 'Não foi possível excluir.') } }
async function exportar(cert) {
  try {
    const response = await api.get(`/certificados-digitais/${cert.id}/download`, { responseType: 'blob' })
    const url = URL.createObjectURL(response.data); const a = document.createElement('a'); a.href = url; a.download = `certificado-${cert.documento || cert.id}.pfx`; a.click(); URL.revokeObjectURL(url)
  } catch(e) { toast.error(e.response?.data?.message || 'Não foi possível exportar.') }
}

async function conectarAssinador(){conectando.value=true;try{const health=await fetch('http://127.0.0.1:17473/health',{cache:'no-store'});if(!health.ok)throw new Error('Assinador indisponível.');const response=await fetch('http://127.0.0.1:17473/certificates',{headers:{'X-IntelbrasTech-Token':pairingToken.value},cache:'no-store'});const data=await response.json();if(!response.ok)throw new Error(data.message||'Pareamento recusado.');conectorOnline.value=true;certificadosDispositivo.value=data.certificates||[];sessionStorage.setItem('intelbrastech-signer-token',pairingToken.value);toast.success(`${certificadosDispositivo.value.length} certificado(s) localizado(s) no dispositivo.`)}catch(e){conectorOnline.value=false;certificadosDispositivo.value=[];toast.error(e.message||'Assinador local não encontrado. Inicie o conector Windows e tente novamente.')}finally{conectando.value=false}}
async function registrarDispositivo(cert){try{const {data}=await api.post('/certificados-digitais/dispositivo',{...cert,tipo:'A1'});toast.success(data.message);await carregar()}catch(e){toast.error(e.response?.data?.message||'Não foi possível registrar o certificado do dispositivo.')}}

function formatarData(v) { if (!v) return 'Não informada'; return new Intl.DateTimeFormat('pt-BR', { timeZone: 'UTC' }).format(new Date(`${v}T00:00:00Z`)) }
function formatarDataHora(v) { if (!v) return 'Não informada'; return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(v)) }
function armazenamentoLabel(v) { return ({ servidor: 'Servidor', cliente: 'Máquina do cliente', windows: 'Windows' }[v] || 'Servidor') }
function statusTitulo(cert) { if (!cert.validade) return 'Validade não informada'; if (cert.dias_para_vencer < 0) return `Certificado vencido em ${formatarData(cert.validade)}`; return `Certificado válido até ${formatarData(cert.validade)}` }
function diasParaVencer(cert) {
  if (cert.dias_para_vencer != null) return Number(cert.dias_para_vencer)
  if (!cert.validade) return null
  const hoje = new Date(); hoje.setHours(0, 0, 0, 0)
  const vencimento = new Date(`${cert.validade}T00:00:00`)
  return Math.ceil((vencimento - hoje) / 86400000)
}
function statusLabel(cert) {
  const dias = diasParaVencer(cert)
  if (dias == null) return 'Status não identificado'
  if (dias < 0) return 'Vencido'
  if (dias === 0) return 'Vence hoje'
  if (dias <= 30) return `Vence em ${dias} dia${dias === 1 ? '' : 's'}`
  return 'Válido'
}
function statusBadge(cert) {
  const dias = diasParaVencer(cert)
  if (dias != null && dias < 0) return 'badge-danger'
  if (dias != null && dias <= 30) return 'badge-warning'
  return 'badge-success'
}
function statusClasse(cert) {
  const dias = diasParaVencer(cert)
  if (dias != null && dias < 0) return { borda: 'border-danger', fundo: 'bg-danger/10', icone: 'text-danger', texto: 'text-danger' }
  if (dias != null && dias <= 30) return { borda: 'border-warning', fundo: 'bg-warning/10', icone: 'text-warning', texto: 'text-warning' }
  return { borda: 'border-success', fundo: 'bg-success/10', icone: 'text-success', texto: 'text-success' }
}

onMounted(()=>{pairingToken.value=sessionStorage.getItem('intelbrastech-signer-token')||'';carregar();fetch('http://127.0.0.1:17473/health',{cache:'no-store'}).then(r=>conectorOnline.value=r.ok).catch(()=>{})})
</script>
