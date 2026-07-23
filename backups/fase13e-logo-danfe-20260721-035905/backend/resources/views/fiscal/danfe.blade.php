<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>DANFE {{ $nfe->numero }}</title>
<style>
@page{margin:18px}*{box-sizing:border-box}body{font-family:DejaVu Sans,Arial,sans-serif;font-size:8px;color:#111;margin:0}
.box{border:1px solid #111;margin-top:4px}.row{display:table;width:100%;table-layout:fixed}.cell{display:table-cell;border-right:1px solid #111;padding:3px;vertical-align:top}.cell:last-child{border-right:0}
.label{font-size:6.5px;text-transform:uppercase}.value{font-size:9px;font-weight:bold;margin-top:2px}.center{text-align:center}.right{text-align:right}.title{font-size:12px;font-weight:bold}.section{font-size:9px;font-weight:bold;margin-top:4px}
table{width:100%;border-collapse:collapse}th,td{border:1px solid #111;padding:2px;font-size:7px}th{font-weight:bold}.items td{height:45px;vertical-align:top}.key{font-family:DejaVu Sans Mono,monospace;font-size:9px;letter-spacing:.3px}.muted{font-size:7px}.canhoto{height:45px}.logo{max-width:100px;max-height:58px}
</style>
</head>
<body>
<div class="box canhoto row">
  <div class="cell" style="width:84%"><div>RECEBEMOS DE {{ mb_strtoupper($nfe->emitente_razao) }} OS PRODUTOS CONSTANTES DA NOTA FISCAL INDICADA AO LADO</div><br>Data de recebimento ____________________ &nbsp;&nbsp; Identificação e assinatura do recebedor ______________________________</div>
  <div class="cell center"><b>NF-e</b><div class="title">Nº {{ str_pad($nfe->numero, 6, '0', STR_PAD_LEFT) }}</div><b>Série {{ $nfe->serie }}</b></div>
</div>

<div class="box row" style="margin-top:12px">
  <div class="cell" style="width:40%">
    @if($nfe->empresa?->logo)<img class="logo" src="{{ $nfe->empresa->logo }}" alt="Logo"><br>@endif
    <div class="value">{{ $nfe->emitente_razao }}</div>
    <div>{{ $nfe->empresa?->logradouro }}, {{ $nfe->empresa?->numero }} - {{ $nfe->empresa?->bairro }}</div>
    <div>{{ $nfe->empresa?->cep }} - {{ $nfe->empresa?->cidade }}/{{ $nfe->empresa?->estado }}</div>
    <div>{{ $nfe->empresa?->telefone }} {{ $nfe->empresa?->email }}</div>
  </div>
  <div class="cell center" style="width:18%"><div class="title">DANFE</div><div>Documento Auxiliar da Nota Fiscal Eletrônica</div><br><b>0 - Entrada<br>1 - Saída</b> &nbsp; <span class="title">1</span><div class="title">Nº {{ str_pad($nfe->numero, 6, '0', STR_PAD_LEFT) }}</div><b>SÉRIE: {{ $nfe->serie }}</b></div>
  <div class="cell center" style="width:42%"><div class="label">Chave de acesso</div><div class="key">{{ $nfe->chave_formatada }}</div><br><div class="muted">Consulta de autenticidade no portal nacional da NF-e<br>www.nfe.fazenda.gov.br/portal</div></div>
</div>

<div class="box row"><div class="cell"><div class="label">Natureza da operação</div><div class="value">{{ $nfe->natureza_operacao }}</div></div><div class="cell"><div class="label">Protocolo de autorização de uso</div><div class="value">{{ $nfe->protocolo }} {{ $nfe->data_autorizacao?->format('d/m/Y H:i:sP') }}</div></div></div>
<div class="box row"><div class="cell"><div class="label">Inscrição Estadual</div><div class="value">{{ $nfe->emitente_ie }}</div></div><div class="cell"><div class="label">CNPJ</div><div class="value">{{ $nfe->emitente_cnpj }}</div></div></div>

<div class="section">Destinatário/Remetente</div>
<div class="box row"><div class="cell" style="width:38%"><div class="label">Nome / Razão Social</div><div class="value">{{ $nfe->destinatario_nome }}</div></div><div class="cell"><div class="label">CNPJ/CPF</div><div class="value">{{ $nfe->destinatario_cpf_cnpj }}</div></div><div class="cell"><div class="label">Data da emissão</div><div class="value">{{ $nfe->data_emissao?->format('d/m/Y') }}</div></div></div>
<div class="box row"><div class="cell"><div class="label">Endereço</div><div class="value">{{ $nfe->destinatario_logradouro }}, {{ $nfe->destinatario_numero }}</div></div><div class="cell"><div class="label">Bairro</div><div class="value">{{ $nfe->destinatario_bairro }}</div></div><div class="cell"><div class="label">CEP</div><div class="value">{{ $nfe->destinatario_cep }}</div></div></div>
<div class="box row"><div class="cell"><div class="label">Município</div><div class="value">{{ $nfe->destinatario_cidade }}</div></div><div class="cell"><div class="label">UF</div><div class="value">{{ $nfe->destinatario_uf }}</div></div><div class="cell"><div class="label">Inscrição Estadual</div><div class="value">{{ $nfe->destinatario_ie }}</div></div></div>

<div class="section">Cálculo do imposto</div>
<div class="box row"><div class="cell"><div class="label">Base de cálculo do ICMS</div><div class="value">{{ number_format($nfe->base_calculo_icms,2,',','.') }}</div></div><div class="cell"><div class="label">Valor do ICMS</div><div class="value">{{ number_format($nfe->valor_icms,2,',','.') }}</div></div><div class="cell"><div class="label">Valor dos produtos</div><div class="value">{{ number_format($nfe->valor_produtos,2,',','.') }}</div></div><div class="cell"><div class="label">Valor total da nota</div><div class="value">{{ number_format($nfe->valor_total,2,',','.') }}</div></div></div>
<div class="box row"><div class="cell"><div class="label">Frete</div><div class="value">{{ number_format($nfe->valor_frete,2,',','.') }}</div></div><div class="cell"><div class="label">Seguro</div><div class="value">{{ number_format($nfe->valor_seguro,2,',','.') }}</div></div><div class="cell"><div class="label">Desconto</div><div class="value">{{ number_format($nfe->valor_desconto,2,',','.') }}</div></div><div class="cell"><div class="label">IPI</div><div class="value">{{ number_format($nfe->valor_ipi,2,',','.') }}</div></div></div>

<div class="section">Transportador/Volumes transportados</div>
<div class="box row"><div class="cell"><div class="label">Frete por conta</div><div class="value">{{ ($nfe->tipo_frete ?: '9') }} - {{ ($nfe->tipo_frete ?: '9') === '9' ? 'Sem ocorrência de transporte' : 'Conforme operação' }}</div></div><div class="cell"><div class="label">Quantidade</div><div class="value">{{ $nfe->volumes ?? 0 }}</div></div><div class="cell"><div class="label">Peso bruto</div><div class="value">{{ number_format($nfe->peso_bruto ?? 0,3,',','.') }}</div></div><div class="cell"><div class="label">Peso líquido</div><div class="value">{{ number_format($nfe->peso_liquido ?? 0,3,',','.') }}</div></div></div>

<div class="section">Itens da nota fiscal</div>
<table class="items"><thead><tr><th>Código</th><th style="width:28%">Descrição do produto/serviço</th><th>NCM/SH</th><th>CSOSN/CST</th><th>CFOP</th><th>UN</th><th>Qtde</th><th>Preço un.</th><th>Total</th><th>BC ICMS</th><th>Vlr. ICMS</th></tr></thead><tbody>
@foreach($itens as $item)<tr><td>{{ $item->codigo }}</td><td>{{ $item->descricao }}</td><td>{{ $item->ncm }}</td><td>{{ $item->icms_origem }}{{ str_pad($item->icms_cst,3,'0',STR_PAD_LEFT) }}</td><td>{{ $item->cfop }}</td><td>{{ $item->unidade }}</td><td class="right">{{ number_format($item->quantidade,2,',','.') }}</td><td class="right">{{ number_format($item->valor_unitario,2,',','.') }}</td><td class="right">{{ number_format($item->valor_total,2,',','.') }}</td><td class="right">{{ number_format($item->icms_base,2,',','.') }}</td><td class="right">{{ number_format($item->icms_valor,2,',','.') }}</td></tr>@endforeach
</tbody></table>

<div class="section">Dados adicionais</div>
<div class="box row" style="height:70px"><div class="cell" style="width:65%"><div class="label">Informações complementares</div>{{ $nfe->informacoes_adicionais }}</div><div class="cell"><div class="label">Reservado ao Fisco</div>{{ $nfe->informacoes_fisco }}</div></div>
<div class="right muted">Documento gerado em {{ now()->format('d/m/Y H:i:s') }} - {{ $nfe->ambiente_nome }}</div>
</body></html>
