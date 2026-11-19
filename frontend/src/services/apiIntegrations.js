import api from './api'

export const apiIntegrations = {
  list: () => api.get('/configuracoes/apis'),
  save: (provider, payload) => api.put(`/configuracoes/apis/${provider}`, payload),
  test: (provider) => api.post(`/configuracoes/apis/${provider}/testar`),
}
