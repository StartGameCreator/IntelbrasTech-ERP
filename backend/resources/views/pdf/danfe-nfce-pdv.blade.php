<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
@page{margin:18px}*{box-sizing:border-box}body{font-family:DejaVu Sans,Arial,sans-serif;font-size:8px;color:#111;margin:0}
.box{border:1px solid #111;margin-top:4px}.row{display:table;width:100%;table-layout:fixed}.cell{display:table-cell;border-right:1px solid #111;padding:3px;vertical-align:top}.cell:last-child{border-right:0}
.label{font-size:6.5px;text-transform:uppercase}.value{font-size:9px;font-weight:bold;margin-top:2px}.center{text-align:center}.right{text-align:right}.title{font-size:12px;font-weight:bold}.section{font-size:9px;font-weight:bold;margin-top:4px}
table{width:100%;border-collapse:collapse}th,td{border:1px solid #111;padding:2px;font-size:7px}th{font-weight:bold}.items td{height:45px;vertical-align:top}.key{font-family:DejaVu Sans Mono,monospace;font-size:8px;letter-spacing:.2px}.muted{font-size:7px}.canhoto{height:45px}.logo{max-width:72px;max-height:58px}.barcode{width:100%;height:32px;margin:2px 0}.qr{width:72px;height:72px;margin:1px auto}.qr-cell{width:13%;padding:3px}.header{height:126px}.small{font-size:6px}.warning{font-size:7px;font-weight:bold}
</style>
</head>
<body>
@php
$nota=$venda->notaFiscal;
$d=$danfeDados ?? [];
$ide=$d['ide'] ?? [];
$dest=$d['destinatario'] ?? [];
$tot=$d['totais'] ?? [];
$itensXml=$d['itens'] ?? [];
$money=fn($v)=>number_format((float)($v ?? 0),2,',','.');
$money3=fn($v)=>number_format((float)($v ?? 0),3,',','.');
$digits=fn($v)=>preg_replace('/\D+/', '', (string)$v);
$formatDoc=function($v) use($digits){$n=$digits($v);if(strlen($n)===14)return substr($n,0,2).'.'.substr($n,2,3).'.'.substr($n,5,3).'/'.substr($n,8,4).'-'.substr($n,12,2);if(strlen($n)===11)return substr($n,0,3).'.'.substr($n,3,3).'.'.substr($n,6,3).'-'.substr($n,9,2);return $v;};
$formatCep=function($v) use($digits){$n=$digits($v);return strlen($n)===8?substr($n,0,5).'-'.substr($n,5):$v;};
$chave=$digits($nota?->chave_acesso);
$chaveFmt=trim(chunk_split($chave,4,' '));
$destDoc=($dest['CNPJ'] ?? null) ?: (($dest['CPF'] ?? null) ?: $nota?->destinatario_cpf_cnpj);
$items=$itensXml;
if(empty($items)){foreach($venda->pedido?->itens ?? [] as $i){$items[]=['codigo'=>$i->codigo ?: $i->produto_id,'descricao'=>$i->descricao,'ncm'=>$i->ncm ?? $i->produto?->ncm,'cst'=>$i->icms_cst ?? '','cfop'=>$i->cfop ?? '','unidade'=>$i->unidade ?? 'UN','quantidade'=>$i->quantidade,'unitario'=>$i->preco_unitario,'total'=>$i->total,'bc_icms'=>$i->icms_base ?? 0,'v_icms'=>$i->icms_valor ?? 0];}}
$nomeDest=($dest['xNome'] ?? null) ?: ($nota?->destinatario_nome ?: 'CONSUMIDOR NÃO IDENTIFICADO');
@endphp

<div class="box canhoto row">
 <div class="cell" style="width:84%">RECEBEMOS DE {{ mb_strtoupper($empresa?->razao_social ?: $empresa?->nome_fantasia) }} OS PRODUTOS CONSTANTES DA NOTA FISCAL INDICADA AO LADO<br><br>Data de recebimento ____________________ &nbsp;&nbsp; Identificação e assinatura do recebedor ______________________________</div>
 <div class="cell center"><b>NFC-e</b><div class="title">Nº {{ str_pad((string)$documento->numero,6,'0',STR_PAD_LEFT) }}</div><b>Série {{ $documento->serie }}</b></div>
</div>

<div class="box row header" style="margin-top:12px">
 <div class="cell" style="width:34%">
  @if($danfeLogoDataUri)<img class="logo" src="{{ $danfeLogoDataUri }}" alt="Logo Intelbras"><br>@endif
  <div class="value">{{ $empresa?->razao_social ?: $empresa?->nome_fantasia }}</div>
  <div>{{ $empresa?->logradouro }}, {{ $empresa?->numero ?: 'S/N' }} - {{ $empresa?->bairro }}</div>
  <div>{{ $formatCep($empresa?->cep) }} - {{ $empresa?->cidade }}/{{ $empresa?->estado }}</div>
  <div>{{ $empresa?->telefone }} {{ $empresa?->email }}</div>
 </div>
 <div class="cell center" style="width:17%"><div class="title">DANFE</div><div>Documento Auxiliar da Nota Fiscal de Consumidor Eletrônica</div><br><b>1 - Saída</b><div class="title">Nº {{ str_pad((string)$documento->numero,6,'0',STR_PAD_LEFT) }}</div><b>SÉRIE: {{ $documento->serie }}</b></div>
 <div class="cell center" style="width:36%">
  <div class="label">Chave de acesso</div>
  @if($danfeBarcodeDataUri)<img class="barcode" src="{{ $danfeBarcodeDataUri }}" alt="Código de barras da chave">@endif
  <div class="key">{{ $chaveFmt }}</div>
  <div class="muted" style="margin-top:5px">Consulta de autenticidade pelo QR Code ou no portal da SEFAZ</div>
 </div>
 <div class="cell center qr-cell"><div class="label">QR Code</div>@if($danfeQrCodeDataUri)<img class="qr" src="{{ $danfeQrCodeDataUri }}" alt="QR Code oficial"><div class="small">Consulte a NFC-e</div>@else<div class="warning" style="margin-top:28px">QR Code indisponível</div>@endif</div>
</div>

<div class="box row"><div class="cell"><div class="label">Natureza da operação</div><div class="value">{{ ($ide['natOp'] ?? null) ?: $nota?->natureza_operacao }}</div></div><div class="cell"><div class="label">Protocolo de autorização de uso</div><div class="value">{{ $nota?->protocolo }} {{ $nota?->data_autorizacao?->format('d/m/Y H:i:sP') }}</div></div></div>
<div class="box row"><div class="cell"><div class="label">Inscrição Estadual</div><div class="value">{{ $empresa?->ie }}</div></div><div class="cell"><div class="label">CNPJ</div><div class="value">{{ $formatDoc($empresa?->cnpj) }}</div></div></div>

<div class="section">Destinatário/Remetente</div>
<div class="box row"><div class="cell" style="width:38%"><div class="label">Nome / Razão Social</div><div class="value">{{ $nomeDest }}</div></div><div class="cell"><div class="label">CNPJ/CPF</div><div class="value">{{ $formatDoc($destDoc) }}</div></div><div class="cell"><div class="label">Data da emissão</div><div class="value">{{ $nota?->data_emissao?->format('d/m/Y') }}</div></div></div>
<div class="box row"><div class="cell"><div class="label">Endereço</div><div class="value">{{ ($dest['xLgr'] ?? null) ?: $nota?->destinatario_logradouro }} {{ ($dest['nro'] ?? null) ?: $nota?->destinatario_numero }}</div></div><div class="cell"><div class="label">Bairro</div><div class="value">{{ ($dest['xBairro'] ?? null) ?: $nota?->destinatario_bairro }}</div></div><div class="cell"><div class="label">CEP</div><div class="value">{{ $formatCep(($dest['CEP'] ?? null) ?: $nota?->destinatario_cep) }}</div></div></div>
<div class="box row"><div class="cell"><div class="label">Município</div><div class="value">{{ ($dest['xMun'] ?? null) ?: $nota?->destinatario_cidade }}</div></div><div class="cell"><div class="label">UF</div><div class="value">{{ ($dest['UF'] ?? null) ?: $nota?->destinatario_uf }}</div></div><div class="cell"><div class="label">Inscrição Estadual</div><div class="value">{{ ($dest['IE'] ?? null) ?: $nota?->destinatario_ie }}</div></div></div>

<div class="section">Cálculo do imposto</div>
<div class="box row"><div class="cell"><div class="label">Base de cálculo do ICMS</div><div class="value">{{ $money($tot['vBC'] ?? $nota?->base_calculo_icms) }}</div></div><div class="cell"><div class="label">Valor do ICMS</div><div class="value">{{ $money($tot['vICMS'] ?? $nota?->valor_icms) }}</div></div><div class="cell"><div class="label">Valor dos produtos</div><div class="value">{{ $money($tot['vProd'] ?? $nota?->valor_produtos) }}</div></div><div class="cell"><div class="label">Valor total da nota</div><div class="value">{{ $money($tot['vNF'] ?? $nota?->valor_total) }}</div></div></div>
<div class="box row"><div class="cell"><div class="label">Frete</div><div class="value">{{ $money($tot['vFrete'] ?? $nota?->valor_frete) }}</div></div><div class="cell"><div class="label">Seguro</div><div class="value">{{ $money($tot['vSeg'] ?? $nota?->valor_seguro) }}</div></div><div class="cell"><div class="label">Desconto</div><div class="value">{{ $money($tot['vDesc'] ?? $nota?->valor_desconto) }}</div></div><div class="cell"><div class="label">IPI</div><div class="value">{{ $money($tot['vIPI'] ?? $nota?->valor_ipi) }}</div></div></div>

<div class="section">Transportador/Volumes transportados</div>
<div class="box row"><div class="cell"><div class="label">Frete por conta</div><div class="value">9 - Sem ocorrência de transporte</div></div><div class="cell"><div class="label">Quantidade</div><div class="value">{{ $nota?->volumes ?? 0 }}</div></div><div class="cell"><div class="label">Peso bruto</div><div class="value">{{ $money3($nota?->peso_bruto) }}</div></div><div class="cell"><div class="label">Peso líquido</div><div class="value">{{ $money3($nota?->peso_liquido) }}</div></div></div>

<div class="section">Itens da nota fiscal</div>
<table class="items"><thead><tr><th>Código</th><th style="width:28%">Descrição do produto/serviço</th><th>NCM/SH</th><th>CSOSN/CST</th><th>CFOP</th><th>UN</th><th>Qtde</th><th>Preço un.</th><th>Total</th><th>BC ICMS</th><th>Vlr. ICMS</th></tr></thead><tbody>
@foreach($items as $i)<tr><td>{{ $i['codigo'] ?? '' }}</td><td>{{ $i['descricao'] ?? '' }}</td><td>{{ $i['ncm'] ?? '' }}</td><td>{{ $i['cst'] ?? '' }}</td><td>{{ $i['cfop'] ?? '' }}</td><td>{{ $i['unidade'] ?? '' }}</td><td class="right">{{ $money($i['quantidade'] ?? 0) }}</td><td class="right">{{ $money($i['unitario'] ?? 0) }}</td><td class="right">{{ $money($i['total'] ?? 0) }}</td><td class="right">{{ $money($i['bc_icms'] ?? 0) }}</td><td class="right">{{ $money($i['v_icms'] ?? 0) }}</td></tr>@endforeach
</tbody></table>

<div class="section">Dados adicionais</div>
<div class="box row" style="height:70px"><div class="cell" style="width:65%"><div class="label">Informações complementares</div>{{ ($d['inf_cpl'] ?? null) ?: $nota?->informacoes_adicionais }}</div><div class="cell"><div class="label">Reservado ao Fisco</div>{{ ($d['inf_ad_fisco'] ?? null) ?: $nota?->informacoes_fisco }}</div></div>
<div class="right muted">Documento gerado em {{ now()->format('d/m/Y H:i:s') }} - {{ $nota?->ambiente_nome }}</div>
</body>
</html>
