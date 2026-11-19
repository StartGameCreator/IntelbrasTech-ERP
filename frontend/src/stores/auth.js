import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'
import router from '@/router'

export const useAuthStore = defineStore('auth', () => {
  const usuario  = ref(null)
  const token    = ref(localStorage.getItem('erp_token') || null)
  const loading  = ref(false)

  const logado        = computed(() => !!token.value)
  const empresa       = computed(() => usuario.value?.empresa ?? null)
  const permissoes    = computed(() => usuario.value?.permissoes ?? [])
  const isAdmin       = computed(() => usuario.value?.admin ?? false)

  function pode(permissao) {
    if (isAdmin.value) return true
    return permissoes.value.includes(permissao)
  }

  async function login(email, senha) {
    loading.value = true
    try {
      const { data } = await api.post('/auth/login', { email, senha })
      token.value   = data.token
      usuario.value = data.usuario
      localStorage.setItem('erp_token', data.token)
      api.defaults.headers.common['Authorization'] = `Bearer ${data.token}`
      router.push('/dashboard')
    } finally {
      loading.value = false
    }
  }

  async function socialExchange(code) {
    if (!code) throw new Error('Código social ausente.')
    const { data } = await api.post('/auth/social/exchange', { code })
    token.value = data.token
    usuario.value = data.usuario
    localStorage.setItem('erp_token', data.token)
    api.defaults.headers.common['Authorization'] = `Bearer ${data.token}`
  }
  async function logout() {
    try { await api.post('/auth/logout') } catch {}
    token.value   = null
    usuario.value = null
    localStorage.removeItem('erp_token')
    delete api.defaults.headers.common['Authorization']
    router.push('/login')
  }

  async function carregarUsuario() {
    if (!token.value) return
    try {
      api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`
      const { data } = await api.get('/auth/me')
      usuario.value = data
    } catch {
      await logout()
    }
  }

  return { usuario, token, loading, logado, empresa, permissoes, isAdmin, pode, login, socialExchange, logout, carregarUsuario }
})
