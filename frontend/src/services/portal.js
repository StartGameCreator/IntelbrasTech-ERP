import api from './api'

export const carregarPortal = () => api.get('/portal/configuracao').then(({ data }) => data)
export const carregarCatalogo = () => api.get('/portal/catalogo').then(({ data }) => data.data || [])
export const carregarCarrossel = () => api.get('/portal/carrossel').then(({ data }) => data.data || [])
export const enviarSolicitacao = payload => api.post('/portal/solicitacoes', payload).then(({ data }) => data)
export const abrirChamado = payload => api.post('/portal/chamados', payload).then(({ data }) => data)
export const acompanharChamado = token => api.get(`/portal/chamados/${token}`).then(({ data }) => data.data)
export const autorizarChamado = (token, payload) => api.post(`/portal/chamados/${token}/autorizar`, payload).then(({ data }) => data.data)
export const contratoChamadoUrl = token => `${api.defaults.baseURL}/portal/chamados/${token}/contrato`
export const carregarUfs = () => api.get('/portal/localidades/ufs').then(({ data }) => data.data || [])
export const carregarMunicipios = uf => api.get(`/portal/localidades/ufs/${uf}/municipios`).then(({ data }) => data.data || [])
export const consultarCep = cep => api.get(`/portal/cep/${String(cep).replace(/\D/g, '')}`).then(({ data }) => data.data)
