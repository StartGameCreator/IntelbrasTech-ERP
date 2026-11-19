<template>
  <form class="portal-form" @submit.prevent="submit">
    <div class="portal-form-grid">
      <label>Nome completo<input v-model.trim="form.nome" required maxlength="200" autocomplete="name" /></label>
      <label>Telefone / WhatsApp<input v-model.trim="form.telefone" required maxlength="30" autocomplete="tel" /></label>
      <label>E-mail<input v-model.trim="form.email" type="email" maxlength="150" autocomplete="email" /></label>
      <label>Cidade<input v-model.trim="form.cidade" maxlength="120" autocomplete="address-level2" /></label>
      <template v-if="type === 'solar'">
        <label>Consumo mensal (kWh)<input v-model.number="form.consumo_kwh" type="number" min="0" /></label>
        <label>Valor médio da conta (R$)<input v-model.number="form.valor_conta" type="number" min="0" step="0.01" /></label>
        <label>Tipo de imóvel<select v-model="form.tipo_imovel"><option value="">Selecione</option><option>Residencial</option><option>Comercial</option><option>Rural</option><option>Industrial</option></select></label>
      </template>
      <template v-if="type === 'assistencia'">
        <label>Equipamento<input v-model.trim="form.equipamento" maxlength="200" /></label>
        <label>Modelo / referência<input v-model.trim="form.modelo" maxlength="150" /></label>
      </template>
    </div>
    <label>{{ type === 'assistencia' ? 'Descreva o defeito' : 'Como podemos ajudar?' }}<textarea v-model.trim="form.mensagem" rows="4" maxlength="3000" /></label>
    <input v-model="form.portal_guard" class="portal-honeypot" name="contact_reference_code" tabindex="-1" autocomplete="one-time-code" aria-hidden="true" />
    <label class="portal-consent"><input v-model="form.consentimento" type="checkbox" required /> Autorizo o uso dos dados para retorno desta solicitação, conforme a política de privacidade.</label>
    <p v-if="success" class="portal-form-success"><CircleCheck /> {{ success }}</p>
    <p v-if="error" class="portal-form-error"><CircleAlert /> {{ error }}</p>
    <button class="portal-button" :disabled="loading">{{ loading ? 'Enviando...' : buttonLabel }}</button>
  </form>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { CircleAlert, CircleCheck } from 'lucide-vue-next'
import { enviarSolicitacao } from '@/services/portal'

const props = defineProps({ type: { type: String, default: 'contato' }, buttonLabel: { type: String, default: 'Enviar solicitação' } })
const blank = () => ({ tipo: props.type, nome: '', telefone: '', email: '', cidade: '', mensagem: '', consumo_kwh: null, valor_conta: null, tipo_imovel: '', equipamento: '', modelo: '', consentimento: false, portal_guard: '' })
const form = reactive(blank())
const loading = ref(false), success = ref(''), error = ref('')
async function submit() {
  loading.value = true; success.value = ''; error.value = ''
  try {
    const result = await enviarSolicitacao(form)
    if (!result?.protocolo || !result?.lead_id) throw new Error('CONFIRMACAO_AUSENTE')
    success.value = `${result.message} Protocolo: ${result.protocolo}`
    Object.assign(form, blank())
  } catch (e) {
    error.value = e.response?.data?.message || 'Não foi possível enviar agora. Tente novamente.'
  } finally { loading.value = false }
}
</script>
