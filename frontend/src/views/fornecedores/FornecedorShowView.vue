<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <button @click="router.push('/fornecedores')" class="text-xs text-[var(--text-muted)] hover:text-[var(--text-primary)] flex items-center gap-1 mb-1">
          <ArrowLeftIcon class="w-3.5 h-3.5" /> Voltar
        </button>
        <h1 class="page-title">{{ fornecedor?.razao_social || 'Fornecedor' }}</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ fornecedor?.nome_fantasia || cpfCnpj(fornecedor?.cpf_cnpj) }}</p>
      </div>
      <div class="flex items-center gap-2" v-if="fornecedor">
        <span class="badge" :class="fornecedor.ativo ? 'badge-success' : 'badge-gray'">{{ fornecedor.ativo ? 'Ativo' : 'Inativo' }}</span>
        <router-link :to="`/fornecedores/${fornecedor.id}/editar`" class="btn-primary btn-sm">
          <PencilIcon class="w-4 h-4" /> Editar
        </router-link>
      </div>
    </div>

    <div v-if="loading" class="skeleton h-40 rounded-xl" />

    <template v-else-if="fornecedor">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="card p-4">
          <p class="text-xs text-[var(--text-muted)]">Produtos Fornecidos</p>
          <p class="text-lg font-semibold">{{ fornecedor.produtos_count ?? 0 }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs text-[var(--text-muted)]">Total Comprado</p>
          <p class="text-lg font-semibold">{{ moeda(fornecedor.total_comprado) }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs text-[var(--text-muted)]">Contas a Pagar em Aberto</p>
          <p class="text-lg font-semibold" :class="fornecedor.contas_pagar_em_aberto > 0 && 'text-warning'">{{ moeda(fornecedor.contas_pagar_em_aberto) }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="card p-4">
          <h3 class="text-sm font-semibold mb-3">Dados Gerais</h3>
          <dl class="space-y-2 text-sm">
            <div><dt class="text-xs text-[var(--text-muted)]">CPF/CNPJ</dt><dd>{{ cpfCnpj(fornecedor.cpf_cnpj) }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">Inscrição Estadual</dt><dd>{{ fornecedor.ie || '—' }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">E-mail</dt><dd>{{ fornecedor.email || '—' }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">Telefone</dt><dd>{{ telefone(fornecedor.telefone) || fornecedor.celular || '—' }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">Contato Principal</dt><dd>{{ fornecedor.contato_principal || '—' }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">Endereço</dt>
              <dd>{{ fornecedor.logradouro }}, {{ fornecedor.numero }} — {{ fornecedor.bairro }}<br>
                  {{ fornecedor.cidade }}/{{ fornecedor.estado }} — {{ cep(fornecedor.cep) }}</dd>
            </div>
          </dl>
        </div>

        <div class="card p-4">
          <h3 class="text-sm font-semibold mb-3">Condições Comerciais</h3>
          <dl class="space-y-2 text-sm">
            <div><dt class="text-xs text-[var(--text-muted)]">Prazo de Entrega</dt><dd>{{ fornecedor.prazo_entrega_dias ?? 0 }} dias</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">Prazo de Pagamento</dt><dd>{{ fornecedor.prazo_pagamento_dias ?? 0 }} dias</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">Desconto Padrão</dt><dd>{{ percentual(fornecedor.desconto_padrao) }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">Dados Bancários</dt>
              <dd>{{ fornecedor.banco || '—' }} <span v-if="fornecedor.agencia">• Ag. {{ fornecedor.agencia }}</span> <span v-if="fornecedor.conta">• CC {{ fornecedor.conta }}</span></dd>
            </div>
            <div><dt class="text-xs text-[var(--text-muted)]">PIX</dt><dd>{{ fornecedor.pix || '—' }}</dd></div>
          </dl>
          <p v-if="fornecedor.observacoes" class="mt-4 pt-4 border-t border-[var(--border)] text-sm text-[var(--text-secondary)]">{{ fornecedor.observacoes }}</p>
        </div>
      </div>
    </template>

    <div v-else class="empty-state">
      <p class="text-sm text-[var(--text-muted)]">Fornecedor não encontrado</p>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useFormato } from '@/composables/useFormato'
import { ArrowLeftIcon, PencilIcon } from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const { moeda, percentual, cpfCnpj, cep, telefone } = useFormato()

const fornecedor = ref(null)
const loading = ref(false)

async function carregar() {
  loading.value = true
  try {
    const { data } = await api.get(`/fornecedores/${route.params.id}`)
    fornecedor.value = data
  } catch {
    fornecedor.value = null
  } finally {
    loading.value = false
  }
}

onMounted(carregar)
</script>
