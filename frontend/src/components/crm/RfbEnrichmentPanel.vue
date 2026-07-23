<template>
  <div class="rfb-panel">
    <div class="rfb-panel__header">
      <div>
        <strong>RFB — Receita Federal do Brasil</strong>
        <small>
          Importa dados cadastrais públicos da empresa e completa o CRM.
        </small>
      </div>

      <button
        type="button"
        class="btn-primary btn-sm"
        :disabled="loading || !cnpj"
        @click="importar"
      >
        {{ loading ? 'Importando...' : 'Importar dados da RFB' }}
      </button>
    </div>

    <p v-if="message" class="rfb-panel__message">{{ message }}</p>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import api from '@/services/api'

const props = defineProps({
  leadId: {
    type: [Number, String],
    required: true,
  },
  cnpj: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['updated'])

const loading = ref(false)
const message = ref('')

async function importar() {
  loading.value = true
  message.value = ''

  try {
    const { data } = await api.post(`/crm/leads/${props.leadId}/enriquecer-cnpj`)
    emit('updated', data.lead)
    message.value = data.message || 'Dados importados com sucesso.'
  } catch (error) {
    message.value =
      error.response?.data?.message ||
      'Não foi possível importar os dados empresariais.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.rfb-panel {
  margin-top: 10px;
  padding: 12px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: color-mix(in srgb, var(--surface) 88%, var(--brand) 12%);
}

.rfb-panel__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.rfb-panel__header strong,
.rfb-panel__header small {
  display: block;
}

.rfb-panel__header small {
  margin-top: 3px;
  color: var(--text-muted);
  font-size: 11px;
}

.rfb-panel__message {
  margin-top: 8px;
  font-size: 12px;
  color: var(--text-muted);
}
</style>
