<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
@page { margin: 13px 14px 16px; }
* { box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 7.4px; color: #000; margin: 0; }
table { width: 100%; border-collapse: collapse; }
td, th { vertical-align: top; }
.box { border: 1px solid #000; }
.cell { border: 1px solid #000; padding: 2px 3px; }
.label { display: block; font-size: 6.2px; line-height: 1.05; }
.value { display: block; font-size: 8px; line-height: 1.15; margin-top: 1px; }
.section-title { font-size: 9px; font-weight: bold; margin: 3px 0 1px; }
.center { text-align: center; }
.right { text-align: right; }
.bold { font-weight: bold; }
.small { font-size: 6.2px; }
.tiny { font-size: 5.6px; }
.receipt { height: 54px; }
.receipt-main { width: 89%; }
.receipt-number { width: 11%; text-align: center; font-weight: bold; font-size: 9px; padding-top: 8px; }
.cut { border-top: 1px dashed #000; margin: 7px 0; }
.header-left { width: 41%; height: 112px; }
.header-center { width: 17%; height: 112px; text-align: center; padding: 5px 3px; }
.header-right { width: 42%; height: 112px; }
.logo-wrap { width: 35%; text-align: center; vertical-align: middle; }
.logo { max-width: 115px; max-height: 73px; }
.company { width: 65%; padding: 5px 4px; vertical-align: middle; }
.company-name { font-size: 9px; font-weight: bold; line-height: 1.12; }
.danfe-title { font-size: 15px; font-weight: bold; }
.danfe-subtitle { font-size: 8px; line-height: 1.05; }
.qr { width: 76px; height: 76px; }
.key { font-size: 8px; letter-spacing: .35px; }
.items th { border: 1px solid #000; padding: 2px 1px; font-size: 6.4px; background: #eee; }
.items td { border-left: 1px solid #000; border-right: 1px solid #000; padding: 2px 1px; font-size: 6.5px; }
.items-body { border-bottom: 1px solid #000; min-height: 142px; }
.item-description { font-size: 6.7px; }
.footer-date { text-align: right; margin-top: 3px; font-size: 6.5px; }
</style>
</head>
<body>
@php
  $nota = $venda->notaFiscal;
  $cliente = $venda->cliente;
  $itens = $venda->pedido?->itens ?? collect();
  $dataEmissao = $nota?->data_emissao ?: $venda->concluida_em;
  $dataAutorizacao = $nota?->data_autorizacao;
  $chave = preg_replace('/\D/', '', (string) $nota?->chave_acesso);
  $chaveFormatada = trim(chunk_split($chave, 4, ' '));
  $valorProdutos = (float) ($nota?->valor_produtos ?? $venda->subtotal ?? 0);
  $valorDesconto = (float) ($nota?->valor_desconto ?? $venda->desconto ?? 0);
  $valorTotal = (float) ($nota?->valor_total ?? $venda->total ?? 0);
  $destNome = $nota?->destinatario_nome ?: ($cliente?->nome ?? 'CONSUMIDOR NÃO IDENTIFICADO');
  $destDoc = $nota?->destinatario_cpf_cnpj ?: ($cliente?->cpf_cnpj ?? '');
@endphp

<table class="receipt">
<tr>
  <td class="cell receipt-main">
    <div class="bold">RECEBEMOS DE {{ mb_strtoupper($empresa?->razao_social ?: $empresa?->nome_fantasia) }} OS PRODUTOS E/OU SERVIÇOS CONSTANTES DA NOTA FISCAL INDICADA AO LADO</div>
    <table style="margin-top:8px"><tr>
      <td style="width:35%; border-right:1px solid #000; height:25px"><span class="label">Data de recebimento</span></td>
      <td style="padding-left:4px"><span class="label">Identificação e assinatura do recebedor</span></td>
    </tr></table>
  </td>
  <td class="cell receipt-number">NFC-e<br><span style="font-size:13px">Nº {{ str_pad((string)$nota?->numero, 6, '0', STR_PAD_LEFT) }}</span><br>Série {{ $nota?->serie }}</td>
</tr>
</table>
<div class="cut"></div>

<table>
<tr>
<td class="cell header-left">
  <table style="height:106px"><tr>
    <td class="logo-wrap">
      @if($danfeLogoDataUri)<img class="logo" src="{{ $danfeLogoDataUri }}" alt="Logo">@endif
    </td>
    <td class="company">
      <div class="company-name">{{ mb_strtoupper($empresa?->razao_social ?: $empresa?->nome_fantasia) }}</div>
      <div>{{ $empresa?->logradouro }}, {{ $empresa?->numero ?: 'S/N' }}@if($empresa?->complemento), {{ $empresa->complemento }}@endif</div>
      <div>{{ $empresa?->bairro }}</div>
      <div>{{ $empresa?->cep }} - {{ $empresa?->cidade }} - {{ $empresa?->estado }}</div>
      @if($empresa?->telefone)<div>Fone {{ $empresa->telefone }}</div>@endif
      @if($empresa?->site)<div>{{ $empresa->site }}</div>@endif
      @if($empresa?->email)<div>{{ $empresa->email }}</div>@endif
    </td>
  </tr></table>
</td>
<td class="cell header-center">
  <div class="danfe-title">DANFE</div>
  <div class="danfe-subtitle">Documento Auxiliar<br>da Nota Fiscal<br>de Consumidor Eletrônica</div>
  <div style="margin-top:4px">Nº <b>{{ str_pad((string)$nota?->numero, 6, '0', STR_PAD_LEFT) }}</b></div>
  <div>SÉRIE: {{ $nota?->serie }}</div>
  <div>Página: 1 de 1</div>
</td>
<td class="cell header-right">
  <table><tr>
    <td style="width:72%; padding:2px 3px">
      <span class="label">Controle do Fisco / Chave de acesso</span>
      <div class="key bold" style="margin:5px 0">{{ $chaveFormatada }}</div>
      <div class="small">Consulta de autenticidade no portal nacional da NF-e/NFC-e ou no site da SEFAZ autorizadora.</div>
      @if($nota?->url_chave)<div class="tiny" style="margin-top:3px">{{ $nota->url_chave }}</div>@endif
    </td>
    <td class="center" style="width:28%; padding:3px">
      @if($danfeQrCodeDataUri)
        <img class="qr" src="{{ $danfeQrCodeDataUri }}" alt="QR Code NFC-e">
        <div class="tiny bold">Consulte pelo QR Code</div>
      @else
        <div class="small" style="padding-top:28px">QR Code indisponível</div>
      @endif
    </td>
  </tr></table>
</td>
</tr>
</table>

<table style="margin-top:3px"><tr>
<td class="cell" style="width:30%"><span class="label">Natureza da operação</span><span class="value">{{ $nota?->natureza_operacao ?: 'Venda de mercadoria' }}</span></td>
<td class="cell" style="width:44%"><span class="label">Protocolo de autorização de uso</span><span class="value">{{ $nota?->protocolo }} @if($dataAutorizacao) {{ $dataAutorizacao->format('d/m/Y H:i:sP') }} @endif</span></td>
<td class="cell" style="width:13%"><span class="label">Inscrição Estadual</span><span class="value">{{ $empresa?->ie_isento ? 'ISENTO' : $empresa?->ie }}</span></td>
<td class="cell" style="width:13%"><span class="label">CNPJ</span><span class="value">{{ $empresa?->cnpjFormatado() ?: $empresa?->cnpj }}</span></td>
</tr></table>

<div class="section-title">Destinatário/Remetente</div>
<table><tr>
<td class="cell" style="width:38%"><span class="label">Nome / Razão Social</span><span class="value">{{ $destNome }}</span></td>
<td class="cell" style="width:22%"><span class="label">CNPJ/CPF</span><span class="value">{{ $destDoc }}</span></td>
<td class="cell" style="width:14%"><span class="label">Inscrição Estadual</span><span class="value">{{ $nota?->destinatario_ie }}</span></td>
<td class="cell" style="width:13%"><span class="label">Data emissão</span><span class="value">{{ $dataEmissao?->format('d/m/Y') }}</span></td>
<td class="cell" style="width:13%"><span class="label">Hora emissão</span><span class="value">{{ $dataEmissao?->format('H:i:s') }}</span></td>
</tr><tr>
<td class="cell"><span class="label">Endereço</span><span class="value">{{ $nota?->destinatario_logradouro }} {{ $nota?->destinatario_numero }}</span></td>
<td class="cell"><span class="label">Bairro</span><span class="value">{{ $nota?->destinatario_bairro }}</span></td>
<td class="cell"><span class="label">CEP</span><span class="value">{{ $nota?->destinatario_cep }}</span></td>
<td class="cell" colspan="2"><span class="label">Município / UF</span><span class="value">{{ $nota?->destinatario_cidade }} / {{ $nota?->destinatario_uf }}</span></td>
</tr></table>

<div class="section-title">Cálculo do imposto</div>
<table><tr>
<td class="cell"><span class="label">Base de cálculo do ICMS</span><span class="value">{{ number_format((float)$nota?->base_calculo_icms,2,',','.') }}</span></td>
<td class="cell"><span class="label">Valor do ICMS</span><span class="value">{{ number_format((float)$nota?->valor_icms,2,',','.') }}</span></td>
<td class="cell"><span class="label">Valor dos produtos</span><span class="value">{{ number_format($valorProdutos,2,',','.') }}</span></td>
<td class="cell"><span class="label">Valor do frete</span><span class="value">{{ number_format((float)$nota?->valor_frete,2,',','.') }}</span></td>
<td class="cell"><span class="label">Desconto</span><span class="value">{{ number_format($valorDesconto,2,',','.') }}</span></td>
<td class="cell"><span class="label">Valor total da nota</span><span class="value bold">{{ number_format($valorTotal,2,',','.') }}</span></td>
</tr></table>

<div class="section-title">Itens da nota fiscal</div>
<table class="items">
<thead><tr>
<th style="width:8%">Código</th><th style="width:38%">Descrição do produto/serviço</th><th style="width:8%">NCM/SH</th><th style="width:6%">CFOP</th><th style="width:4%">UN</th><th style="width:7%">Qtde</th><th style="width:9%">Preço un.</th><th style="width:10%">Preço total</th><th style="width:5%">ICMS</th><th style="width:5%">IPI</th>
</tr></thead>
<tbody class="items-body">
@forelse($itens as $item)
<tr>
<td>{{ $item->produto_id ?? $item->codigo ?? '' }}</td>
<td class="item-description">{{ $item->descricao }}</td>
<td>{{ $item->ncm ?? $item->produto?->ncm ?? '' }}</td>
<td>{{ $item->cfop ?? '' }}</td>
<td>{{ $item->unidade ?? $item->produto?->unidade?->sigla ?? 'UN' }}</td>
<td class="right">{{ number_format((float)$item->quantidade,2,',','.') }}</td>
<td class="right">{{ number_format((float)$item->preco_unitario,2,',','.') }}</td>
<td class="right">{{ number_format((float)$item->total,2,',','.') }}</td>
<td class="right">{{ number_format((float)($item->valor_icms ?? 0),2,',','.') }}</td>
<td class="right">{{ number_format((float)($item->valor_ipi ?? 0),2,',','.') }}</td>
</tr>
@empty
<tr><td colspan="10" class="center">Nenhum item localizado.</td></tr>
@endforelse
<tr><td colspan="10" style="height:105px"></td></tr>
</tbody>
</table>

<div class="section-title">Pagamentos</div>
<table><tr>
@forelse($venda->pagamentos ?? [] as $pagamento)
<td class="cell"><span class="label">Forma de pagamento</span><span class="value">{{ mb_strtoupper(str_replace('_',' ', $pagamento->tipo)) }} — R$ {{ number_format((float)$pagamento->valor,2,',','.') }}</span></td>
@empty
<td class="cell"><span class="value">Sem informações de pagamento.</span></td>
@endforelse
@if((float)$venda->troco > 0)<td class="cell"><span class="label">Troco</span><span class="value">R$ {{ number_format((float)$venda->troco,2,',','.') }}</span></td>@endif
</tr></table>

<div class="section-title">Dados adicionais</div>
<table><tr>
<td class="cell" style="width:52%; height:75px"><span class="label">Observações</span><span class="value small">{{ $nota?->informacoes_adicionais }}</span></td>
<td class="cell" style="width:48%; height:75px"><span class="label">Reservado ao fisco</span><span class="value small">{{ $nota?->informacoes_fisco }}</span></td>
</tr></table>
<div class="footer-date">Documento gerado em {{ now()->format('d/m/Y H:i:s') }} — Ambiente: {{ mb_strtoupper($documento->ambiente) }}</div>
</body>
</html>
