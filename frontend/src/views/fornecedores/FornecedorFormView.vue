<template>
  <div class="space-y-5 animate-fade-in max-w-4xl">
    <div class="page-header">
      <div class="flex items-center gap-4">
        <button @click="$router.back()" class="btn-ghost btn-icon"><ArrowLeftIcon class="w-5 h-5" /></button>
        <h1 class="page-title">{{ isEdit ? 'Editar Fornecedor' : 'Novo Fornecedor' }}</h1>
      </div>
    </div>

    <form @submit.prevent="salvar" class="space-y-4">
      <div class="card p-5 space-y-4">
        <h3 class="text-sm font-semibold border-b border-[var(--border)] pb-2">Dados Principais</h3>
        <div class="form-row-2">
          <div class="form-group">
            <label class="label">Tipo</label>
            <select v-model="form.tipo" class="select">
              <option value="J">Pessoa Jurídica</option>
              <option value="F">Pessoa Física</option>
            </select>
          </div>
          <div class="form-group">
            <label class="label">CPF/CNPJ</label>
            <input v-model="form.cpf_cnpj" class="input" />
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="label">Razão Social *</label>
            <input v-model="form.razao_social" class="input" required />
            <p v-if="erros.razao_social" class="form-error">{{ erros.razao_social[0] }}</p>
          </div>
          <div class="form-group">
            <label class="label">Nome Fantasia</label>
            <input v-model="form.nome_fantasia" class="input" />
          </div>
        </div>
        <div class="form-row-3">
          <div class="form-group">
            <label class="label">Inscrição Estadual</label>
            <input v-model="form.ie" class="input" />
          </div>
          <div class="form-group">
            <label class="label">E-mail</label>
            <input v-model="form.email" type="email" class="input" />
          </div>
          <div class="form-group">
            <label class="label">Contato Principal</label>
            <input v-model="form.contato_principal" class="input" />
          </div>
        </div>
        <div class="form-row-3">
          <div class="form-group">
            <label class="label">Telefone</label>
            <input v-model="form.telefone" class="input" />
          </div>
          <div class="form-group">
            <label class="label">Celular</label>
            <input v-model="form.celular" class="input" />
          </div>
          <div class="form-group">
            <label class="label">WhatsApp</label>
            <input v-model="form.whatsapp" class="input" />
          </div>
        </div>
      </div>

      <div class="card p-5 space-y-4">
        <h3 class="text-sm font-semibold border-b border-[var(--border)] pb-2">Endereço</h3>
        <div class="form-row-3">
          <div class="form-group">
            <label class="label">CEP</label>
            <input v-model="form.cep" class="input" />
          </div>
          <div class="form-group md:col-span-2">
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
            <label class="label">Bairro</label>
            <input v-model="form.bairro" class="input" />
          </div>
          <div class="form-group">
            <label class="label">Complemento</label>
            <input v-model="form.complemento" class="input" />
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="label">Cidade</label>
            <input v-model="form.cidade" class="input" />
          </div>
          <div class="form-group">
            <label class="label">Estado (UF)</label>
            <input v-model="form.estado" class="input" maxlength="2" />
          </div>
        </div>
      </div>

      <div class="card p-5 space-y-4">
        <h3 class="text-sm font-semibold border-b border-[var(--border)] pb-2">Condições Comerciais</h3>
        <div class="form-row-3">
          <div class="form-group">
            <label class="label">Prazo de Entrega (dias)</label>
            <input v-model.number="form.prazo_entrega_dias" type="number" min="0" class="input" />
          </div>
          <div class="form-group">
            <label class="label">Prazo de Pagamento (dias)</label>
            <input v-model.number="form.prazo_pagamento_dias" type="number" min="0" class="input" />
          </div>
          <div class="form-group">
            <label class="label">Desconto Padrão (%)</label>
            <input v-model.number="form.desconto_padrao" type="number" step="0.01" min="0" max="100" class="input" />
          </div>
        </div>
        <div class="form-row-3">
          <div class="form-group">
            <label class="label">Banco</label>
            <input v-model="form.banco" class="input" />
          </div>
          <div class="form-group">
            <label class="label">Agência</label>
            <input v-model="form.agencia" class="input" />
          </div>
          <div class="form-group">
            <label class="label">Conta</label>
            <input v-model="form.conta" class="input" />
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="label">PIX</label>
            <input v-model="form.pix" class="input" />
          </div>
          <div class="form-group">
            <label class="label">Avaliação (0 a 5)</label>
            <input v-model.number="form.avaliacao" type="number" min="0" max="5" class="input" />
          </div>
        </div>
        <div class="form-group">
          <label class="label">Observações</label>
          <textarea v-model="form.observacoes" class="input min-h-20" />
        </div>
      </div>

      <div class="flex items-center justify-between">
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" v-model="form.ativo" /> Fornecedor ativo
        </label>
        <div class="flex items-center gap-3">
          <button type="button" @click="$router.back()" class="btn-secondary">Cancelar</button>
          <button type="submit" class="btn-primary" :disabled="salvando">
            <ArrowPathIcon v-if="salvando" class="w-4 h-4 animate-spin" />
            {{ salvando ? 'Salvando...' : 'Salvar Fornecedor' }}
          </button>
        </div>
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

const form = ref({
  tipo: 'J', razao_social: '', nome_fantasia: '', cpf_cnpj: '', ie: '',
  email: '', contato_principal: '', telefone: '', celular: '', whatsapp: '',
  cep: '', logradouro: '', numero: '', bairro: '', complemento: '', cidade: '', estado: '',
  prazo_entrega_dias: 0, prazo_pagamento_dias: 0, desconto_padrao: 0,
  banco: '', agencia: '', conta: '', pix: '', avaliacao: null, observacoes: '',
  ativo: true,
})

async function salvar() {
  salvando.value = true
  erros.value = {}
  try {
    if (isEdit.value) {
      await api.put(`/fornecedores/${route.params.id}`, form.value)
      toast.success('Fornecedor atualizado com sucesso.')
    } else {
      await api.post('/fornecedores', form.value)
      toast.success('Fornecedor cadastrado com sucesso.')
    }
    router.push('/fornecedores')
  } catch (err) {
    if (err.response?.data?.errors) erros.value = err.response.data.errors
    else toast.error(err.response?.data?.message || 'Erro ao salvar fornecedor.')
  } finally {
    salvando.value = false
  }
}

onMounted(async () => {
  if (isEdit.value) {
    const { data } = await api.get(`/fornecedores/${route.params.id}`)
    Object.assign(form.value, data)
  }
})
</script>
