import api from './api'

export const systemLogsService = {
  async diagnostics() {
    const { data } = await api.get('/sistema/diagnostico')
    return data
  },

  async logs(params = {}) {
    const { data } = await api.get('/sistema/logs', { params })
    return data
  },
}

export default systemLogsService
