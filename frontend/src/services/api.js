import axios from 'axios'
import { useToast } from 'vue-toastification'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://127.0.0.1:8033/api/v1',
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


/* MOTOR_FISCAL_FASE15_api */
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const resposta = error?.response
    const requisicao = error?.config

    if (
      resposta?.status === 409 &&
      resposta?.data?.code === 'NFCE_DATA_EMISSAO_DESATUALIZADA' &&
      requisicao &&
      !requisicao.__confirmacaoDataNfce
    ) {
      const dados = resposta.data
      const mensagem =
        'ATUALIZAÇÃO DA DATA DE EMISSÃO\n\n' +
        'A venda foi realizada em ' + dados.data_venda + '.\n' +
        'A NFC-e está com emissão em ' + dados.data_emissao_fiscal + '.\n\n' +
        'Para transmitir hoje, ' + dados.data_atual +
        ', é necessário atualizar a data e a hora fiscal.\n\n' +
        'Confirma a atualização e deseja continuar a emissão?'

      if (!window.confirm(mensagem)) {
        return Promise.reject(error)
      }

      requisicao.__confirmacaoDataNfce = true

      let payload = {}
      if (typeof requisicao.data === 'string' && requisicao.data) {
        try {
          payload = JSON.parse(requisicao.data)
        } catch {
          payload = {}
        }
      } else if (requisicao.data && typeof requisicao.data === 'object') {
        payload = requisicao.data
      }

      requisicao.data = JSON.stringify({
        ...payload,
        confirmar_atualizacao_data: true,
      })

      requisicao.headers = {
        ...(requisicao.headers || {}),
        'Content-Type': 'application/json',
      }

      return api.request(requisicao)
    }

    return Promise.reject(error)
  },
)

export default api
