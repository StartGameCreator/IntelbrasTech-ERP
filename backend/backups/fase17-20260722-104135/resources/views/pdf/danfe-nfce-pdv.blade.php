<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
@page { margin: 10px; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; margin: 0; }
.center { text-align: center; }
.line { border-top: 1px dashed #333; margin: 7px 0; }
.row { display: table; width: 100%; margin: 3px 0; }
.row span, .row strong { display: table-cell; }
.row strong { text-align: right; }
.item { margin: 6px 0; }
.item strong { display: block; }
.total { font-size: 13px; font-weight: bold; }
.small { font-size: 7px; }
h2 { font-size: 12px; margin: 0 0 4px; }
.logo { display:block; max-width:130px; max-height:58px; margin:0 auto 6px; }
.company-address { font-size:8px; line-height:1.35; }
</style>
</head>
<body>
<div class="center">
  @if($empresa?->logo_data_uri)
    @if($danfeLogoDataUri)
    <img
        src="{{ $danfeLogoDataUri }}"
        alt="Logo"
        style="display:block; width:auto; height:auto; max-width:150px; max-height:58px; margin:0 auto; object-fit:contain;"
    >
@endiflogo_data_uri }}" alt="Logo da empresa">
  @endif
  <h2>{{ $empresa?->nome_fantasia ?: $empresa?->razao_social }}</h2>
  @if($empresa?->nome_fantasia && $empresa?->razao_social)
    <div>{{ $empresa->razao_social }}</div>
  @endif
  <div>CNPJ: {{ $empresa?->cnpjFormatado() ?: $empresa?->cnpj }}</div>
  <div>IE: {{ $empresa?->ie_isento ? 'ISENTO' : $empresa?->ie }}</div>
  <div class="company-address">
    {{ $empresa?->logradouro }}, {{ $empresa?->numero ?: 'S/N' }}
    @if($empresa?->bairro) - {{ $empresa->bairro }} @endif
    <br>
    {{ $empresa?->cidade }}/{{ $empresa?->estado }}
    @if($empresa?->cep) - CEP {{ $empresa->cep }} @endif
  </div>
</div>

<div class="line"></div>
<div class="center"><strong>DANFE NFC-e — Documento Auxiliar</strong></div>
<div class="row"><span>Venda</span><strong>#{{ $venda->numero }}</strong></div>
<div class="row"><span>Data</span><strong>{{ optional($venda->concluida_em)->format('d/m/Y H:i:s') }}</strong></div>
<div class="row"><span>Série / Número</span><strong>{{ $documento->serie }} / {{ $documento->numero }}</strong></div>
<div class="row"><span>Ambiente</span><strong>{{ strtoupper($documento->ambiente) }}</strong></div>

<div class="line"></div>
@foreach($venda->pedido?->itens ?? [] as $item)
<div class="item">
  <strong>{{ $item->descricao }}</strong>
  <div class="row">
    <span>{{ number_format((float)$item->quantidade, 3, ',', '.') }} {{ $item->unidade }} x R$ {{ number_format((float)$item->preco_unitario, 2, ',', '.') }}</span>
    <strong>R$ {{ number_format((float)$item->total, 2, ',', '.') }}</strong>
  </div>
</div>
@endforeach

<div class="line"></div>
<div class="row"><span>Subtotal</span><strong>R$ {{ number_format((float)$venda->subtotal, 2, ',', '.') }}</strong></div>
@if((float)$venda->desconto > 0)
<div class="row"><span>Desconto</span><strong>- R$ {{ number_format((float)$venda->desconto, 2, ',', '.') }}</strong></div>
@endif
<div class="row total"><span>TOTAL</span><strong>R$ {{ number_format((float)$venda->total, 2, ',', '.') }}</strong></div>

<div class="line"></div>
@foreach($venda->pagamentos ?? [] as $pagamento)
<div class="row"><span>{{ strtoupper(str_replace('_',' ', $pagamento->tipo)) }}</span><strong>R$ {{ number_format((float)$pagamento->valor, 2, ',', '.') }}</strong></div>
@endforeach
@if((float)$venda->troco > 0)
<div class="row"><span>Troco</span><strong>R$ {{ number_format((float)$venda->troco, 2, ',', '.') }}</strong></div>
@endif

<div class="line"></div>
<div class="center small">
  <div>Documento fiscal em ambiente de {{ $documento->ambiente }}.</div>
  <div>Status: {{ $documento->status }}</div>
  <div>Este PDF representa o documento gerado pelo módulo fiscal do PDV.</div>
</div>
</body>
</html>
