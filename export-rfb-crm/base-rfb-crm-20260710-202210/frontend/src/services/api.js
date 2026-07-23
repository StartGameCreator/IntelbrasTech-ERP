import axios from 'axios'
import { useToast } from 'vue-toastification'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api/v1',
  timeout: 30000,
  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
})

// Request interceptor
api.interceptors.request.use(config => {
  const token = localStorage.getItem('erp_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

// Response interceptor
api.interceptors.response.use(
  res => res,
  err => {
    const toast = useToast()
    const status = err.response?.status
    const msg    = err.response?.data?.message

    if (status === 401) {
      localStorage.removeItem('erp_token')
      window.location.href = '/login'
    } else if (status === 403) {
      toast.error('Você não tem permissão para esta ação.')
    } else if (status === 422) {
      // Validation errors — tratados nos componentes
    } else if (status === 429) {
      toast.warning('Muitas requisições. Aguarde um momento.')
    } else if (status >= 500) {
      toast.error('Erro interno do servidor. Tente novamente.')
    } else if (!err.response) {
      toast.error('Sem conexão com o servidor.')
    }

    return Promise.reject(err)
  }
)

export default api
