<template>
  <div class="space-y-5 animate-fade-in max-w-4xl">
    <div class="page-header">
      <div class="flex items-center gap-4">
        <button @click="$router.back()" class="btn-ghost btn-icon"><ArrowLeftIcon class="w-5 h-5" /></button>
        <h1 class="page-title">{{ isEdit ? 'Editar Cliente' : 'Novo Cliente' }}</h1>
      </div>
    </div>
    <form @submit.prevent="salvar" class="space-y-4">
      <div class="card p-5 space-y-4">
        <h3 class="text-sm font-semibold border-b border-[var(--border)] pb-2">Dados Principais</h3>
        <div class="form-row-2">
          <div class="form-group">
            <label class="label">Tipo</label>
            <select v-model="form.tipo" class="select">
              <option value="F">Pessoa Física</option>
              <option value="J">Pessoa Jurídica</option>
            </select>
          </div>
          <div class="form-group">
            <label class="label">{{ form.tipo === 'J' ? 'CNPJ' : 'CPF' }}</label>
            <input v-model="form.cpf_cnpj" class="input" :placeholder="form.tipo === 'J' ? '00.000.000/0000-00' : '000.000.000-00'" />
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="label">{{ form.tipo === 'J' ? 'Razão Social *' : 'Nome Completo *' }}</label>
            <input v-model="form.razao_social" class="input" required />
            <p v-if="erros.razao_social" class="form-error">{{ erros.razao_social[0] }}</p>
          </div>
          <div class="form-group" v-if="form.tipo === 'J'">
            <label class="label">Nome Fantasia</label>
            <input v-model="form.nome_fantasia" class="input" />
          </div>
        </div>
        <div class="form-row-3">
          <div class="form-group">
            <label class="label">E-mail</label>
            <input v-model="form.email" type="email" class="input" />
          </div>
          <div class="form-group">
            <label class="label">Celular</label>
            <input v-model="form.celular" class="input" placeholder="(00) 00000-0000" />
          </div>
          <div class="form-group">
            <label class="label">WhatsApp</label>
            <input v-model="form.whatsapp" class="input" placeholder="(00) 00000-0000" />
          </div>
        </div>
      </div>
      <div class="card p-5 space-y-4">
        <h3 class="text-sm font-semibold border-b border-[var(--border)] pb-2">Endereço</h3>
        <div class="form-row-3">
          <div class="form-group">
            <label class="label">CEP</label>
            <input v-model="form.cep" class="input" placeholder="00000-000" @blur="buscarCep" maxlength="9" />
          </div>
          <div class="form-group col-span-2">
            <label class="label">Logradouro</label>
            <input v-model="form.logradouro" class="input" />
          </div>
        </div>
        <div class="form-row-3">
          <div class="form-group">
            <label class="label">Número</label>
            <input v-model="form.numero" class="input" />
          </div>
          <div class="form-group">
            <label class="label">Complemento</label>
            <input v-model="form.complemento" class="input" />
          </div>
          <div class="form-group">
            <label class="label">Bairro</label>
            <input v-model="form.bairro" class="input" />
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="label">Cidade</label>
            <input v-model="form.cidade" class="input" />
          </div>
          <div class="form-group">
            <label class="label">Estado</label>
            <select v-model="form.estado" class="select">
              <option value="">Selecione</option>
              <option v-for="uf in estados" :key="uf" :value="uf">{{ uf }}</option>
            </select>
          </div>
        </div>
      </div>
      <div class="card p-5 space-y-4">
        <h3 class="text-sm font-semibold border-b border-[var(--border)] pb-2">Observações</h3>
        <textarea v-model="form.observacoes" class="input min-h-24" placeholder="Observações sobre o cliente..." />
      </div>
      <div class="flex items-center justify-end gap-3">
        <button type="button" @click="$router.back()" class="btn-secondary">Cancelar</button>
        <button type="submit" class="btn-primary" :disabled="salvando">
          <ArrowPathIcon v-if="salvando" class="w-4 h-4 animate-spin" />
          {{ salvando ? 'Salvando...' : 'Salvar Cliente' }}
        </button>
      </div>
    </form>
  </div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { ArrowLeftIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'
const router = useRouter()
const route  = useRoute()
const toast  = useToast()
const isEdit = computed(() => !!route.params.id)
const salvando = ref(false)
const erros = ref({})
const form = ref({ tipo:'J', razao_social:'', nome_fantasia:'', cpf_cnpj:'', email:'', celular:'', whatsapp:'', cep:'', logradouro:'', numero:'', complemento:'', bairro:'', cidade:'', estado:'', ibge:'', observacoes:'' })
const estados = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO']
async function buscarCep() {
  const cep = form.value.cep?.replace(/\D/g,'')
  if (cep?.length !== 8) return
  try {
    const { data } = await api.get(`/cep/${cep}`)
    if (!data.erro) {
      form.value.logradouro = data.logradouro
      form.value.bairro = data.bairro
      form.value.cidade = data.localidade
      form.value.estado = data.uf
      form.value.ibge = data.ibge
    }
  } catch {}
}
async function salvar() {
  salvando.value = true; erros.value = {}
  try {
    if (isEdit.value) {
      await api.put(`/clientes/${route.params.id}`, form.value)
      toast.success('Cliente atualizado com sucesso.')
    } else {
      await api.post('/clientes', form.value)
      toast.success('Cliente cadastrado com sucesso.')
    }
    router.push('/clientes')
  } catch(err) {
    if (err.response?.data?.errors) erros.value = err.response.data.errors
    else toast.error(err.response?.data?.message || 'Erro ao salvar.')
  } finally { salvando.value = false }
}
onMounted(async () => {
  if (isEdit.value) {
    const { data } = await api.get(`/clientes/${route.params.id}`)
    Object.assign(form.value, data.cliente)
  }
})
</script>
