<template>
  <div class="min-h-screen bg-dark-900 flex items-center justify-center p-4">

    <!-- Background glow -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-40 -left-40 w-96 h-96 bg-brand-500/10 rounded-full blur-3xl" />
      <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-brand-700/10 rounded-full blur-3xl" />
    </div>

    <div class="w-full max-w-sm relative z-10 animate-slide-up">

      <!-- Logo -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-brand-500 shadow-glow mb-4">
          <span class="text-white font-bold text-2xl">IT</span>
        </div>
        <h1 class="text-2xl font-bold text-[var(--text-primary)]">IntelbrasTech ERP</h1>
        <p class="text-sm text-[var(--text-muted)] mt-1">Sistema de Gestão Empresarial</p>
      </div>

      <!-- Form -->
      <div class="card p-6">
        <form @submit.prevent="handleLogin" class="space-y-4">

          <div class="form-group">
            <label class="label">E-mail</label>
            <input
              v-model="form.email"
              type="email"
              class="input"
              placeholder="seu@email.com"
              autocomplete="email"
              :disabled="loading"
            />
            <p v-if="erros.email" class="form-error">{{ erros.email }}</p>
          </div>

          <div class="form-group">
            <label class="label">Senha</label>
            <div class="relative">
              <input
                v-model="form.senha"
                :type="mostrarSenha ? 'text' : 'password'"
                class="input pr-10"
                placeholder="••••••••"
                autocomplete="current-password"
                :disabled="loading"
              />
              <button
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--text-muted)] hover:text-[var(--text-secondary)]"
                @click="mostrarSenha = !mostrarSenha"
              >
                <EyeIcon v-if="!mostrarSenha" class="w-4 h-4" />
                <EyeSlashIcon v-else class="w-4 h-4" />
              </button>
            </div>
            <p v-if="erros.senha" class="form-error">{{ erros.senha }}</p>
          </div>

          <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="lembrar" class="rounded border-[var(--border)] bg-[var(--bg-input)] text-brand-500" />
              <span class="text-xs text-[var(--text-secondary)]">Lembrar-me</span>
            </label>
            <a href="/esqueci-senha" class="text-xs text-brand-400 hover:text-brand-300">
              Esqueci a senha
            </a>
          </div>

          <button
            type="submit"
            class="btn-primary w-full justify-center"
            :disabled="loading"
          >
            <ArrowPathIcon v-if="loading" class="w-4 h-4 animate-spin" />
            <span>{{ loading ? 'Entrando...' : 'Entrar' }}</span>
          </button>


          <div class="flex items-center gap-3 py-1">
            <div class="h-px flex-1 bg-[var(--border)]"></div>
            <span class="text-[11px] text-[var(--text-muted)] uppercase">ou continue com</span>
            <div class="h-px flex-1 bg-[var(--border)]"></div>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <button v-for="p in socialProviders" :key="p.id" type="button" class="btn-secondary justify-center" :disabled="socialLoading" @click="socialLogin(p.id)">
              <SocialBrandLogo :provider="p.id" class="w-5 h-5 shrink-0" /><span>{{ p.label }}</span>
            </button>
          </div>

          <p v-if="erroGeral" class="text-sm text-danger text-center bg-danger/10 rounded-lg p-3">
            {{ erroGeral }}
          </p>

        </form>
        <div class="marketplace-signup">
          <span>Ainda não possui cadastro?</span>
        </div>
      </div>

      
        <RouterLink to="/cadastre-se" class="mt-4 block text-center text-xs font-semibold text-brand-400 hover:text-brand-300">Não possui conta? Escolha seu perfil e cadastre-se</RouterLink>
<!-- Footer -->
      <p class="text-center text-xs text-[var(--text-muted)] mt-6">
        © {{ ano }} IntelbrasTech · Todos os direitos reservados
      </p>
    </div>
  </div>
</template>

<script setup>
import { RouterLink } from 'vue-router'
import { ref } from 'vue'
import api from '@/services/api'
import SocialBrandLogo from '@/components/branding/SocialBrandLogo.vue'
import { useAuthStore } from '@/stores/auth'
import { EyeIcon, EyeSlashIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

const auth = useAuthStore()

const form = ref({ email: '', senha: '' })
const erros = ref({})
const erroGeral = ref('')
const loading = ref(false)
const mostrarSenha = ref(false)
const lembrar = ref(false)
const ano = new Date().getFullYear()


const socialLoading = ref(false)
const socialProviders = [
  { id: 'google', label: 'Google' },
  { id: 'microsoft', label: 'Microsoft' },
  { id: 'apple', label: 'Apple' },
  { id: 'facebook', label: 'Facebook' },
]
async function socialLogin(provider) {
  erroGeral.value = ''
  socialLoading.value = true
  try {
    const { data } = await api.get(`/auth/social/${provider}/redirect`)
    window.location.assign(data.url)
  } catch (err) {
    erroGeral.value = err.response?.data?.message || 'Não foi possível iniciar o login social.'
    socialLoading.value = false
  }
}
async function handleLogin() {
  erros.value = {}
  erroGeral.value = ''

  if (!form.value.email) { erros.value.email = 'Informe o e-mail.'; return }
  if (!form.value.senha)  { erros.value.senha = 'Informe a senha.'; return }

  loading.value = true
  try {
    await auth.login(form.value.email, form.value.senha)
  } catch (err) {
    const data = err.response?.data
    if (data?.errors) {
      Object.assign(erros.value, data.errors)
    } else {
      erroGeral.value = data?.message || 'Credenciais inválidas.'
    }
  } finally {
    loading.value = false
  }
}
</script>


<style scoped>
.marketplace-signup {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 14px;
  font-size: 0.68rem;
  color: var(--text-muted);
}
.marketplace-signup a {
  color: var(--primary);
  font-weight: 700;
}
.marketplace-signup a:hover {
  text-decoration: underline;
}
</style>
