<template>
  <div class="min-h-screen bg-dark-900 flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
      <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-brand-500 text-white font-bold text-xl mb-3">IT</div>
        <h1 class="text-xl font-bold text-[var(--text-primary)]">Redefinir senha</h1>
        <p class="text-sm text-[var(--text-muted)] mt-1">Recupere o acesso ao IntelbrasTech ERP</p>
      </div>

      <div class="card p-6 space-y-4">
        <template v-if="!token">
          <div>
            <label class="label">E-mail</label>
            <input v-model.trim="email" type="email" class="input" autocomplete="email" @keyup.enter="solicitar" />
          </div>
          <button class="btn-primary w-full justify-center" :disabled="loading || !email" @click="solicitar">
            {{ loading ? 'Verificando...' : 'Continuar' }}
          </button>
        </template>

        <template v-else>
          <div class="rounded-lg bg-brand-500/10 p-3 text-xs text-brand-300">
            Usuário confirmado no ambiente local. Defina uma nova senha com pelo menos 8 caracteres.
          </div>
          <div>
            <label class="label">Nova senha</label>
            <input v-model="senha" type="password" class="input" autocomplete="new-password" />
          </div>
          <div>
            <label class="label">Confirmar nova senha</label>
            <input v-model="confirmacao" type="password" class="input" autocomplete="new-password" @keyup.enter="redefinir" />
          </div>
          <button class="btn-primary w-full justify-center" :disabled="loading || !podeRedefinir" @click="redefinir">
            {{ loading ? 'Salvando...' : 'Salvar nova senha' }}
          </button>
        </template>

        <p v-if="mensagem" class="rounded-lg p-3 text-sm text-center" :class="sucesso ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger'">{{ mensagem }}</p>
        <RouterLink to="/login" class="btn-secondary w-full justify-center">Voltar ao login</RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'

const router = useRouter()
const email = ref('elvis@intelbrastech.com.br')
const token = ref('')
const senha = ref('')
const confirmacao = ref('')
const loading = ref(false)
const mensagem = ref('')
const sucesso = ref(false)
const podeRedefinir = computed(() => senha.value.length >= 8 && senha.value === confirmacao.value)

async function solicitar() {
  loading.value = true; mensagem.value = ''; sucesso.value = false
  try {
    const { data } = await api.post('/auth/esqueci-senha', { email: email.value })
    if (data.debug_token) token.value = data.debug_token
    else { sucesso.value = true; mensagem.value = data.message }
  } catch (e) {
    mensagem.value = e.response?.data?.message || 'Não foi possível iniciar a recuperação.'
  } finally { loading.value = false }
}

async function redefinir() {
  if (!podeRedefinir.value) { mensagem.value = 'As senhas precisam ser iguais e ter pelo menos 8 caracteres.'; return }
  loading.value = true; mensagem.value = ''
  try {
    await api.post('/auth/redefinir-senha', {
      email: email.value, token: token.value,
      senha_nova: senha.value, senha_nova_confirmation: confirmacao.value,
    })
    sucesso.value = true; mensagem.value = 'Senha alterada. Redirecionando para o login...'
    setTimeout(() => router.push('/login'), 900)
  } catch (e) {
    sucesso.value = false
    mensagem.value = e.response?.data?.errors?.token?.[0] || e.response?.data?.message || 'Não foi possível alterar a senha.'
  } finally { loading.value = false }
}
</script>
