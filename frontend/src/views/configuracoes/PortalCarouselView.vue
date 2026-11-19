<template>
  <div class="content-page">
    <header class="page-head">
      <div><span>PORTAL PÚBLICO</span><h1>Conteúdo do carrossel</h1><p>Controle os produtos, serviços e campanhas exibidos na página inicial.</p></div>
      <button class="primary" @click="novo"><PlusIcon /> Novo item</button>
    </header>

    <section class="stats">
      <article><small>Total</small><strong>{{ items.length }}</strong></article>
      <article><small>Ativos</small><strong>{{ items.filter(i => i.ativo).length }}</strong></article>
      <article><small>Agendados</small><strong>{{ items.filter(i => i.publicar_em).length }}</strong></article>
    </section>

    <section v-if="editing" class="editor">
      <div class="editor-head"><div><h2>{{ form.id ? 'Editar item' : 'Novo item' }}</h2><p>Use uma imagem horizontal com boa resolução.</p></div><button class="icon" @click="cancelar"><XMarkIcon /></button></div>
      <form @submit.prevent="salvar" class="form-grid">
        <label><span>Tipo *</span><select v-model="form.tipo" required><option value="servico">Serviço</option><option value="produto">Produto</option><option value="campanha">Campanha</option></select></label>
        <label><span>Categoria</span><input v-model="form.categoria" placeholder="Ex.: Energia solar" /></label>
        <label class="wide"><span>Título *</span><input v-model="form.titulo" required maxlength="180" /></label>
        <label class="wide"><span>Descrição</span><textarea v-model="form.descricao" rows="3"></textarea></label>
        <label class="wide"><span>URL da imagem</span><input v-model="form.imagem_url" type="url" placeholder="https://..." /></label>
        <label><span>Link de destino</span><input v-model="form.link_url" placeholder="/energia-solar ou https://..." /></label>
        <label><span>Texto do botão</span><input v-model="form.texto_link" placeholder="Saiba mais" /></label>
        <label><span>Publicar em</span><input v-model="form.publicar_em" type="datetime-local" /></label>
        <label><span>Expirar em</span><input v-model="form.expirar_em" type="datetime-local" /></label>
        <label class="check"><input v-model="form.ativo" type="checkbox" /> Item ativo</label>
        <label class="check"><input v-model="form.link_externo" type="checkbox" /> Abrir link em nova aba</label>
        <div v-if="form.imagem_url" class="preview wide" :style="{ backgroundImage: `url('${form.imagem_url}')` }"><span>Pré-visualização</span></div>
        <div class="actions wide"><button type="button" class="secondary" @click="cancelar">Cancelar</button><button class="primary" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar item' }}</button></div>
      </form>
    </section>

    <section class="list-card">
      <div class="list-head"><h2>Itens publicados</h2><span>Use as setas para alterar a ordem</span></div>
      <div v-if="loading" class="empty">Carregando conteúdo...</div>
      <div v-else-if="!items.length" class="empty"><PhotoIcon /><b>Nenhum conteúdo personalizado</b><p>O portal está usando o conteúdo padrão. Crie o primeiro item para assumir o controle do carrossel.</p></div>
      <article v-for="(item, index) in items" :key="item.id" class="item-row">
        <div class="thumb" :style="item.imagem_url ? { backgroundImage: `url('${item.imagem_url}')` } : {}"><PhotoIcon v-if="!item.imagem_url" /></div>
        <div class="item-copy"><div><span class="badge">{{ item.tipo }}</span><span :class="['status', item.ativo ? 'on' : 'off']">{{ item.ativo ? 'Ativo' : 'Inativo' }}</span></div><h3>{{ item.titulo }}</h3><p>{{ item.categoria || 'Sem categoria' }} · {{ item.descricao || 'Sem descrição' }}</p></div>
        <div class="row-actions"><button :disabled="index === 0" title="Mover para cima" @click="mover(index, -1)"><ChevronUpIcon /></button><button :disabled="index === items.length - 1" title="Mover para baixo" @click="mover(index, 1)"><ChevronDownIcon /></button><button title="Editar" @click="editar(item)"><PencilIcon /></button><button class="danger" title="Excluir" @click="excluir(item)"><TrashIcon /></button></div>
      </article>
    </section>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ChevronDownIcon, ChevronUpIcon, PencilIcon, PhotoIcon, PlusIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const toast = useToast(); const items = ref([]); const loading = ref(true); const saving = ref(false); const editing = ref(false)
const empty = () => ({ id:null, tipo:'servico', categoria:'', titulo:'', descricao:'', imagem_url:'', link_url:'', texto_link:'Saiba mais', link_externo:false, ativo:true, publicar_em:'', expirar_em:'' })
const form = reactive(empty())
const normalizeDate = value => value ? String(value).slice(0,16) : ''
async function carregar(){ loading.value=true; try { items.value=(await api.get('/portal/conteudo/carrossel')).data.data } finally { loading.value=false } }
function novo(){ Object.assign(form, empty()); editing.value=true; window.scrollTo({ top:0, behavior:'smooth' }) }
function editar(item){ Object.assign(form, item, { publicar_em:normalizeDate(item.publicar_em), expirar_em:normalizeDate(item.expirar_em) }); editing.value=true; window.scrollTo({ top:0, behavior:'smooth' }) }
function cancelar(){ editing.value=false; Object.assign(form, empty()) }
async function salvar(){ saving.value=true; try { const payload={...form, publicar_em:form.publicar_em||null, expirar_em:form.expirar_em||null, ordem:form.ordem||items.value.length+1}; form.id ? await api.put(`/portal/conteudo/carrossel/${form.id}`,payload) : await api.post('/portal/conteudo/carrossel',payload); toast.success('Conteúdo do carrossel salvo.'); cancelar(); await carregar() } catch(e){ toast.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||'Não foi possível salvar.') } finally { saving.value=false } }
async function excluir(item){ if(!window.confirm(`Excluir "${item.titulo}" do carrossel?`)) return; await api.delete(`/portal/conteudo/carrossel/${item.id}`); toast.success('Item removido.'); await carregar() }
async function mover(index, direction){ const target=index+direction; if(target<0||target>=items.value.length)return; [items.value[index],items.value[target]]=[items.value[target],items.value[index]]; await api.post('/portal/conteudo/carrossel/ordenar',{ids:items.value.map(i=>i.id)}); toast.success('Ordem atualizada.') }
onMounted(carregar)
</script>

<style scoped>
.content-page{padding:24px;max-width:1450px;margin:auto;color:#e8edf7}.page-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:22px}.page-head span{font-size:11px;letter-spacing:.12em;color:#6d8eff;font-weight:800}.page-head h1{font-size:28px;font-weight:850;margin-top:5px}.page-head p,.list-head span{color:#8290a8;font-size:13px;margin-top:5px}.primary,.secondary,.icon,.row-actions button{border-radius:8px;display:inline-flex;align-items:center;justify-content:center;gap:7px}.primary{background:#5d5fef;color:white;padding:11px 16px;font-weight:750}.primary svg{width:18px}.secondary{border:1px solid #313a54;padding:10px 16px}.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px}.stats article,.editor,.list-card{background:#111424;border:1px solid #272c44;border-radius:14px}.stats article{padding:17px}.stats small{color:#8490a7}.stats strong{display:block;font-size:25px;margin-top:5px}.editor{padding:20px;margin-bottom:18px}.editor-head,.list-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}.editor-head h2,.list-head h2{font-size:18px;font-weight:800}.editor-head p{font-size:12px;color:#8490a7}.icon{width:36px;height:36px;border:1px solid #30364d}.icon svg,.row-actions svg{width:17px}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.form-grid label span{display:block;font-size:11px;font-weight:700;margin-bottom:6px;color:#aeb8ca}.form-grid input,.form-grid select,.form-grid textarea{width:100%;background:#0b0e1b;border:1px solid #30364d;border-radius:8px;padding:10px 12px;color:#f4f6fb}.wide{grid-column:1/-1}.check{display:flex!important;align-items:center;gap:8px;font-size:12px}.check input{width:auto}.preview{height:190px;background-position:center;background-size:cover;border-radius:11px;position:relative;overflow:hidden}.preview span{position:absolute;left:10px;bottom:10px;background:#080b15cc;padding:5px 8px;border-radius:5px}.actions{display:flex;justify-content:flex-end;gap:10px}.list-card{padding:20px}.item-row{display:grid;grid-template-columns:128px 1fr auto;gap:16px;align-items:center;padding:14px 0;border-top:1px solid #252a40}.thumb{height:78px;border-radius:9px;background:#0b0e1b center/cover;display:grid;place-items:center}.thumb svg{width:27px;color:#536079}.item-copy>div{display:flex;gap:7px}.badge,.status{font-size:9px;text-transform:uppercase;padding:4px 7px;border-radius:999px;background:#252b47}.status.on{background:#123c2d;color:#65e5a8}.status.off{background:#3c2229;color:#fa8798}.item-copy h3{font-size:15px;font-weight:800;margin-top:7px}.item-copy p{font-size:11px;color:#8490a7;margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:700px}.row-actions{display:flex;gap:6px}.row-actions button{width:34px;height:34px;border:1px solid #30364d}.row-actions button:disabled{opacity:.25}.row-actions .danger{color:#ff8798}.empty{text-align:center;padding:45px;color:#8490a7}.empty svg{width:42px;margin:auto auto 10px}.empty b{display:block;color:#dfe5f1}.empty p{font-size:12px;margin-top:5px}@media(max-width:750px){.content-page{padding:15px}.page-head{flex-direction:column}.stats{grid-template-columns:1fr}.form-grid{grid-template-columns:1fr}.wide{grid-column:auto}.item-row{grid-template-columns:76px 1fr}.thumb{height:65px}.row-actions{grid-column:1/-1;justify-content:flex-end}.item-copy p{max-width:230px}}
</style>
