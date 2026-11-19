export function useFormato() {
  const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })
  const num = new Intl.NumberFormat('pt-BR')
  const dec = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

  function moeda(v) {
    return brl.format(parseFloat(v) || 0)
  }

  function moedaCompacto(v) {
    const n = parseFloat(v) || 0
    if (n >= 1e6) return `R$ ${(n / 1e6).toFixed(1)}M`
    if (n >= 1e3) return `R$ ${(n / 1e3).toFixed(1)}K`
    return brl.format(n)
  }

  function numero(v) {
    return num.format(parseInt(v) || 0)
  }

  function decimal(v, casas = 2) {
    return new Intl.NumberFormat('pt-BR', {
      minimumFractionDigits: casas,
      maximumFractionDigits: casas,
    }).format(parseFloat(v) || 0)
  }

  function percentual(v) {
    return `${decimal(v)}%`
  }

  function data(v) {
    if (!v) return '—'
    return new Date(v).toLocaleDateString('pt-BR')
  }

  function dataHora(v) {
    if (!v) return '—'
    return new Date(v).toLocaleString('pt-BR', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
    })
  }

  function cpfCnpj(v) {
    if (!v) return '—'
    const d = v.replace(/\D/g, '')
    if (d.length === 11) return d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4')
    return d.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5')
  }

  function cep(v) {
    if (!v) return '—'
    const d = v.replace(/\D/g, '')
    return d.replace(/(\d{5})(\d{3})/, '$1-$2')
  }

  function telefone(v) {
    if (!v) return '—'
    const d = v.replace(/\D/g, '')
    if (d.length === 11) return d.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3')
    return d.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3')
  }

  function peso(v) {
    const n = parseFloat(v) || 0
    if (n >= 1000) return `${decimal(n / 1000, 3)} kg`
    return `${decimal(n, 0)} g`
  }

  function statusLabel(status) {
    const map = {
      orcamento: 'Orçamento', confirmado: 'Confirmado', faturado: 'Faturado',
      entregue: 'Entregue', cancelado: 'Cancelado',
      aberto: 'Em Aberto', pago: 'Pago', vencido: 'Vencido', parcial: 'Parcial',
      autorizada: 'Autorizada', rascunho: 'Rascunho', processando: 'Processando',
    }
    return map[status] || status
  }

  return { moeda, moedaCompacto, numero, decimal, percentual, data, dataHora, cpfCnpj, cep, telefone, peso, statusLabel }
}
