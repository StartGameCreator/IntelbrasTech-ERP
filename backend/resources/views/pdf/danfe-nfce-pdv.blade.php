<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
@page { margin: 14px 14px 12px; }
* { box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 7.2px; color:#000; margin:0; }
table { width:100%; border-collapse:collapse; table-layout:fixed; }
td, th { border:1px solid #000; padding:2px 3px; vertical-align:top; overflow-wrap:anywhere; }
.no-border td { border:0; }
.bold { font-weight:700; }
.center { text-align:center; }
.right { text-align:right; }
.label { display:block; font-size:6px; line-height:1.05; text-transform:none; }
.value { display:block; font-size:8px; line-height:1.15; margin-top:1px; }
.section-title { font-size:9px; font-weight:bold; margin:3px 0 0; }
.receipt { height:52px; }
.dashed { border-top:1px dashed #000; margin:8px 0; }
.header-main { height:126px; }
.logo { max-width:120px; max-height:82px; display:block; margin:auto; }
.barcode { width:100%; height:44px; display:block; }
.qr { width:84px; height:84px; display:block; margin:2px auto; }
.danfe-title { font-size:18px; font-weight:bold; }
.danfe-num { font-size:13px; font-weight:bold; }
.key { font-size:8px; letter-spacing:.15px; }
.items th { font-size:6.2px; text-align:center; padding:2px 1px; }
.items td { font-size:6.6px; padding:2px 2px; }
.items-body td { height:70px; }
.tax-row td { height:44px; vertical-align:middle; }
.tax-heading { font-size:10px; font-weight:bold; text-align:center; }
.tax-sub { font-size:7px; text-align:center; }
.tax-value { font-size:10px; font-weight:bold; }
.footer-date { text-align:right; font-size:7px; margin-top:2px; }
.small { font-size:6.2px; }
</style>
</head>
<body>
@php
$nota = $venda->notaFiscal;
$d = $danfeDados ?? [];
$ide = $d['ide'] ?? [];
$dest = $d['destinatario'] ?? [];
$tot = $d['totais'] ?? [];
$transp = $d['transportadora'] ?? [];
$veic = $d['veiculo'] ?? [];
$vol = $d['volumes'] ?? [];
$itensXml = $d['itens'] ?? [];
$tributos = $danfeTributos ?? [];
$money = fn($v) => number_format((float)($v ?? 0), 2, ',', '.');
$money3 = fn($v) => number_format((float)($v ?? 0), 3, ',', '.');
$digits = fn($v) => preg_replace('/\D+/', '', (string)$v);
$formatDoc = function($v) use ($digits) {
    $n=$digits($v);
    if(strlen($n)===14) return substr($n,0,2).'.'.substr($n,2,3).'.'.substr($n,5,3).'/'.substr($n,8,4).'-'.substr($n,12,2);
    if(strlen($n)===11) return substr($n,0,3).'.'.substr($n,3,3).'.'.substr($n,6,3).'-'.substr($n,9,2);
    return $v;
};
$formatCep = function($v) use ($digits) { $n=$digits($v); return strlen($n)===8 ? substr($n,0,5).'-'.substr($n,5) : $v; };
$formatPhone = function($v) use ($digits) { $n=$digits($v); return strlen($n)>=10 ? '('.substr($n,0,2).') '.substr($n,2,-4).'-'.substr($n,-4) : $v; };
$chave = $digits($nota?->chave_acesso);
$chaveFmt = trim(chunk_split($chave,4,' '));
$emissao = $nota?->data_emissao;
$saida = !empty($ide['dhSaiEnt']) ? \Carbon\Carbon::parse($ide['dhSaiEnt']) : $emissao;
$aut = $nota?->data_autorizacao;
$destDoc = $dest['CNPJ'] ?: ($dest['CPF'] ?: $nota?->destinatario_cpf_cnpj);
$freteMap = ['0'=>'0 - Contratação do Frete por conta do Remetente','1'=>'1 - Contratação do Frete por conta do Destinatário','2'=>'2 - Contratação do Frete por conta de Terceiros','3'=>'3 - Transporte Próprio por conta do Remetente','4'=>'4 - Transporte Próprio por conta do Destinatário','9'=>'9 - Sem Ocorrência de Transporte'];
$modFrete = (string)($transp['modFrete'] ?? $nota?->tipo_frete ?? '9');
$freteDesc = $freteMap[$modFrete] ?? $modFrete;
$items = $itensXml;
if(empty($items)) {
  foreach($venda->pedido?->itens ?? [] as $i) {
    $items[]=['codigo'=>$i->produto_id ?? '', 'descricao'=>$i->descricao, 'ncm'=>$i->ncm ?? $i->produto?->ncm, 'cst'=>$i->icms_cst ?? '', 'cfop'=>$i->cfop ?? '', 'unidade'=>$i->unidade ?? $i->produto?->unidade?->sigla ?? 'UN', 'quantidade'=>$i->quantidade, 'unitario'=>$i->preco_unitario, 'total'=>$i->total, 'bc_icms'=>$i->icms_base ?? 0, 'v_icms'=>$i->icms_valor ?? 0, 'v_ipi'=>$i->ipi_valor ?? 0, 'p_icms'=>$i->icms_aliquota ?? 0, 'p_ipi'=>$i->ipi_aliquota ?? 0];
  }
}
$obs = $d['inf_cpl'] ?: $nota?->informacoes_adicionais;
$reservado = $d['inf_ad_fisco'] ?: $nota?->informacoes_fisco;
@endphp

<table class="receipt">
<tr>
<td colspan="3" class="bold">RECEBEMOS DE {{ mb_strtoupper($empresa?->razao_social ?: $empresa?->nome_fantasia) }} OS PRODUTOS CONSTANTES DA NOTA FISCAL INDICADA AO LADO</td>
<td rowspan="2" class="center" style="width:11%"><span class="value bold">NF-e</span><span class="danfe-num">Nº {{ str_pad((string)$documento->numero,6,'0',STR_PAD_LEFT) }}</span><span class="value bold">Série {{ $documento->serie }}</span></td>
</tr>
<tr>
<td style="width:31%"><span class="label">Data de recebimento</span></td>
<td style="width:45%"><span class="label">Identificação e assinatura do recebedor</span></td>
<td style="width:13%" class="center"><span class="value">{{ $freteDesc }}</span><span class="value">{{ now()->format('d/m/Y H:i:s') }}</span></td>
</tr>
</table>

<div class="dashed"></div>

<table class="header-main">
<tr>
<td style="width:41%">
<table class="no-border"><tr>
<td style="width:39%; vertical-align:middle">@if($danfeLogoDataUri)<img class="logo" src="{{ $danfeLogoDataUri }}">@endif</td>
<td style="width:61%; vertical-align:middle">
<div class="value bold">{{ mb_strtoupper($empresa?->razao_social ?: $empresa?->nome_fantasia) }}</div>
<div class="value">{{ $empresa?->logradouro }}, {{ $empresa?->numero ?: 'S/N' }}@if($empresa?->bairro), {{ $empresa->bairro }}@endif</div>
<div class="value">{{ $formatCep($empresa?->cep) }} - {{ $empresa?->cidade }} - {{ $empresa?->estado }}</div>
@if($empresa?->telefone)<div class="value">Fone {{ $formatPhone($empresa->telefone) }}</div>@endif
@if($empresa?->site)<div class="value">{{ $empresa->site }}</div>@endif
@if($empresa?->email)<div class="value">{{ $empresa->email }}</div>@endif
</td></tr></table>
</td>
<td style="width:14%" class="center">
<div class="danfe-title">DANFE</div>
<div class="value">Documento Auxiliar<br>da Nota Fiscal<br>Eletrônica</div>
<div class="value">0-Entrada<br>1-Saída <span style="border:1px solid #000;padding:4px;margin-left:5px">{{ $ide['tpNF'] ?? 1 }}</span></div>
<div class="danfe-num">Nº {{ str_pad((string)$documento->numero,6,'0',STR_PAD_LEFT) }}</div>
<div class="value">SÉRIE: {{ $documento->serie }}<br>Página: 1 de 1</div>
</td>
<td style="width:33%">
<span class="label">Controle do Fisco</span>
@if($danfeBarcodeDataUri)<img class="barcode" src="{{ $danfeBarcodeDataUri }}">@endif
<div style="border-top:1px solid #000;margin:2px -3px 0;padding:2px 3px">
<span class="label">Chave de acesso</span>
<div class="key center">{{ $chaveFmt }}</div>
</div>
<div style="border-top:1px solid #000;margin:2px -3px 0;padding:4px 3px" class="value">
@if(!empty($danfeConsultaUrl))
Consulta da NFC-e no endereço oficial da SEFAZ:<br>
{{ $danfeConsultaUrl }}
@else
Consulta da NFC-e pelo QR Code oficial da SEFAZ
@endif
</div>
</td>
<td style="width:12%" class="center">
<span class="label">QR CODE</span>
@if($danfeQrCodeDataUri)<img class="qr" src="{{ $danfeQrCodeDataUri }}" alt="QR Code oficial da NFC-e"><div class="small">Consulte a autenticidade da NFC-e</div>@else<div class="value" style="margin-top:25px">QR Code oficial indisponível no XML</div>@endif
</td>
</tr>
</table>

<table style="margin-top:3px">
<tr><td style="width:30%"><span class="label">Natureza da operação</span><span class="value">{{ $ide['natOp'] ?: ($nota?->natureza_operacao ?: 'Venda de mercadoria') }}</span></td>
<td style="width:70%"><span class="label">Protocolo de autorização de uso</span><span class="value">{{ $nota?->protocolo }} @if($aut) {{ $aut->format('d/m/Y H:i:sP') }} @endif</span></td></tr>
<tr><td><span class="label">Inscrição Estadual</span><span class="value">{{ $empresa?->ie_isento ? 'ISENTO' : $empresa?->ie }}</span></td>
<td style="padding:0"><table class="no-border"><tr><td style="width:50%;border-right:1px solid #000"><span class="label">Inscr.est. do subst.trib.</span><span class="value">{{ $empresa?->ie_substituto ?? '' }}</span></td><td><span class="label">CNPJ</span><span class="value">{{ $formatDoc($empresa?->cnpj) }}</span></td></tr></table></td></tr>
</table>

<div class="section-title">Destinatário/Remetente</div>
<table>
<tr><td style="width:38%"><span class="label">Nome / Razão Social</span><span class="value">{{ $dest['xNome'] ?: $nota?->destinatario_nome }}</span></td>
<td style="width:22%"><span class="label">CNPJ/CPF</span><span class="value">{{ $formatDoc($destDoc) }}</span></td>
<td style="width:22%"><span class="label">Inscrição Estadual</span><span class="value">{{ $dest['IE'] ?: $nota?->destinatario_ie }}</span></td>
<td rowspan="3" style="width:18%;padding:0"><table class="no-border"><tr><td style="border-bottom:1px solid #000"><span class="label">Data emissão</span><span class="value">{{ $emissao?->format('d/m/Y') }}</span></td></tr><tr><td style="border-bottom:1px solid #000"><span class="label">Data saída</span><span class="value">{{ $saida?->format('d/m/Y') }}</span></td></tr><tr><td><span class="label">Hora saída</span><span class="value">{{ $saida?->format('H:i:s') }}</span></td></tr></table></td>
</tr>
<tr><td><span class="label">Endereço</span><span class="value">{{ $dest['xLgr'] ?: $nota?->destinatario_logradouro }}, {{ $dest['nro'] ?: $nota?->destinatario_numero }} @if($dest['xCpl'])- {{ $dest['xCpl'] }}@endif</span></td>
<td><span class="label">Bairro</span><span class="value">{{ $dest['xBairro'] ?: $nota?->destinatario_bairro }}</span></td>
<td><span class="label">CEP</span><span class="value">{{ $formatCep($dest['CEP'] ?: $nota?->destinatario_cep) }}</span></td></tr>
<tr><td><span class="label">Município</span><span class="value">{{ $dest['xMun'] ?: $nota?->destinatario_cidade }}</span></td>
<td><span class="label">UF</span><span class="value">{{ $dest['UF'] ?: $nota?->destinatario_uf }}</span></td>
<td><span class="label">Fone/Fax</span><span class="value">{{ $formatPhone($dest['fone'] ?? '') }}</span></td></tr>
</table>

<div class="section-title">Faturas</div>
<table><tr>
@for($i=0;$i<3;$i++)
<td style="width:10%" class="bold">Número</td><td style="width:15%" class="bold">Vencimento</td><td style="width:8%" class="bold">Valor</td>
@endfor
</tr></table>

<div class="section-title">Cálculo do imposto</div>
<table>
<tr>
<td><span class="label">Base de cálculo do ICMS</span><span class="value">{{ $money($tot['vBC'] ?? $nota?->base_calculo_icms) }}</span></td>
<td><span class="label">Valor do ICMS</span><span class="value">{{ $money($tot['vICMS'] ?? $nota?->valor_icms) }}</span></td>
<td><span class="label">Base de cálculo do ICMS Subst.</span><span class="value">{{ $money($tot['vBCST'] ?? 0) }}</span></td>
<td><span class="label">Valor do ICMS Subst.</span><span class="value">{{ $money($tot['vST'] ?? 0) }}</span></td>
<td><span class="label">Valor do FCP ST</span><span class="value">{{ $money($tot['vFCPST'] ?? 0) }}</span></td>
<td><span class="label">Valor total dos produtos</span><span class="value">{{ $money($tot['vProd'] ?? $nota?->valor_produtos) }}</span></td>
</tr><tr>
<td><span class="label">Valor do frete</span><span class="value">{{ $money($tot['vFrete'] ?? $nota?->valor_frete) }}</span></td>
<td><span class="label">Valor do seguro</span><span class="value">{{ $money($tot['vSeg'] ?? $nota?->valor_seguro) }}</span></td>
<td><span class="label">Desconto</span><span class="value">{{ $money($tot['vDesc'] ?? $nota?->valor_desconto) }}</span></td>
<td><span class="label">Outras despesas acessórias</span><span class="value">{{ $money($tot['vOutro'] ?? $nota?->outras_despesas) }}</span></td>
<td><span class="label">Valor do IPI</span><span class="value">{{ $money($tot['vIPI'] ?? $nota?->valor_ipi) }}</span></td>
<td><span class="label">Valor total da nota</span><span class="value bold">{{ $money($tot['vNF'] ?? $nota?->valor_total) }}</span></td>
</tr>
</table>

<div class="section-title">Transportador/Volumes transportados</div>
<table>
<tr>
<td style="width:29%"><span class="label">Nome</span><span class="value">{{ $transp['xNome'] ?? '' }}</span></td>
<td style="width:18%"><span class="label">Frete por conta</span><span class="value">{{ $freteDesc }}</span></td>
<td style="width:16%"><span class="label">Código ANTT</span><span class="value">{{ $veic['RNTC'] ?? '' }}</span></td>
<td style="width:17%"><span class="label">Placa do veículo</span><span class="value">{{ $veic['placa'] ?? '' }}</span></td>
<td style="width:5%"><span class="label">UF</span><span class="value">{{ $veic['UF'] ?? '' }}</span></td>
<td style="width:15%"><span class="label">CNPJ/CPF</span><span class="value">{{ $formatDoc(($transp['CNPJ'] ?? '') ?: ($transp['CPF'] ?? '')) }}</span></td>
</tr><tr>
<td><span class="label">Endereço</span><span class="value">{{ $transp['xEnder'] ?? '' }}</span></td>
<td colspan="2"><span class="label">Município</span><span class="value">{{ $transp['xMun'] ?? '' }}</span></td>
<td><span class="label">UF</span><span class="value">{{ $transp['UF'] ?? '' }}</span></td>
<td colspan="2"><span class="label">Inscrição Estadual</span><span class="value">{{ $transp['IE'] ?? '' }}</span></td>
</tr><tr>
<td><span class="label">Quantidade</span><span class="value">{{ $vol['qVol'] ?? $nota?->volumes ?? 0 }}</span></td>
<td><span class="label">Espécie</span><span class="value">{{ $vol['esp'] ?? $nota?->especie }}</span></td>
<td><span class="label">Marca</span><span class="value">{{ $vol['marca'] ?? '' }}</span></td>
<td><span class="label">Numeração</span><span class="value">{{ $vol['nVol'] ?? '' }}</span></td>
<td><span class="label">Peso bruto</span><span class="value">{{ $money3($vol['pesoB'] ?? $nota?->peso_bruto) }}</span></td>
<td><span class="label">Peso líquido</span><span class="value">{{ $money3($vol['pesoL'] ?? $nota?->peso_liquido) }}</span></td>
</tr>
</table>

<div class="section-title">Itens da nota fiscal</div>
<table class="items">
<thead><tr>
<th style="width:7%">Código</th><th style="width:33%">Descrição do produto/serviço</th><th style="width:7%">NCM/SH</th><th style="width:6%">CSOSN</th><th style="width:5%">CFOP</th><th style="width:3%">UN</th><th style="width:4%">Qtde</th><th style="width:7%">Preço un</th><th style="width:8%">Preço total</th><th style="width:6%">BC ICMS</th><th style="width:6%">Vlr.ICMS</th><th style="width:5%">Vlr.IPI</th><th style="width:5%">%ICMS</th><th style="width:4%">%IPI</th>
</tr></thead><tbody class="items-body">
@forelse($items as $i)
<tr>
<td>{{ $i['codigo'] }}</td><td>{{ $i['descricao'] }}</td><td>{{ $i['ncm'] }}</td><td>{{ $i['cst'] }}</td><td>{{ $i['cfop'] }}</td><td>{{ $i['unidade'] }}</td>
<td class="right">{{ $money($i['quantidade']) }}</td><td class="right">{{ $money($i['unitario']) }}</td><td class="right">{{ $money($i['total']) }}</td>
<td class="right">{{ $money($i['bc_icms']) }}</td><td class="right">{{ $money($i['v_icms']) }}</td><td class="right">{{ $money($i['v_ipi']) }}</td>
<td class="right">{{ number_format((float)$i['p_icms'],4,',','.') }}</td><td class="right">{{ $money($i['p_ipi']) }}</td>
</tr>
@empty
<tr><td colspan="14" class="center">Nenhum item localizado.</td></tr>
@endforelse
<tr><td colspan="14" style="height:55px"></td></tr>
</tbody></table>

<div class="section-title">Cálculo do ISSQN</div>
<table><tr>
<td><span class="label">Inscrição Municipal</span><span class="value">{{ $empresa?->inscricao_municipal ?? $empresa?->im ?? '' }}</span></td>
<td><span class="label">Valor total dos serviços</span><span class="value">{{ $money($tot['vServ'] ?? 0) }}</span></td>
<td><span class="label">Base de cálculo do ISSQN</span><span class="value">{{ $money($tot['vBCISS'] ?? 0) }}</span></td>
<td><span class="label">Valor do ISSQN</span><span class="value">{{ $money($tot['vISS'] ?? 0) }}</span></td>
</tr></table>

<div class="section-title">Dados adicionais</div>
<table><tr>
<td style="width:50%;height:72px"><span class="label">Observações</span><span class="value">{{ $obs }}</span></td>
<td style="width:50%;height:72px"><span class="label">Reservado ao fisco</span><span class="value">{{ $reservado }}</span></td>
</tr></table>

<table class="tax-row" style="margin-top:4px">
<tr><td colspan="4" style="padding:3px"><div class="tax-heading">TRANSPARÊNCIA TRIBUTÁRIA (Lei Federal 12.741/2012)</div><div class="tax-sub">Valor aproximado dos tributos incidentes sobre os produtos/serviços. Fonte: {{ $tributos['fonte'] ?? 'IBPT' }}.</div></td></tr>
<tr>
<td class="center" style="width:25%"><div class="bold">FEDERAIS</div>
@if(!empty($tributos['composicao_informada']))<div class="tax-value">R$ {{ $money($tributos['federais'] ?? 0) }}</div><div>({{ number_format((float)($tributos['percentual_federal'] ?? 0),2,',','.') }}%)</div>@else<div class="value">Composição não informada separadamente</div>@endif
</td>
<td class="center" style="width:25%"><div class="bold">ESTADUAIS</div>
@if(!empty($tributos['composicao_informada']))<div class="tax-value">R$ {{ $money($tributos['estaduais'] ?? 0) }}</div><div>({{ number_format((float)($tributos['percentual_estadual'] ?? 0),2,',','.') }}%)</div>@else<div class="value">Composição não informada separadamente</div>@endif
</td>
<td class="center" style="width:25%"><div class="bold">MUNICIPAIS</div>
@if(!empty($tributos['composicao_informada']))<div class="tax-value">R$ {{ $money($tributos['municipais'] ?? 0) }}</div><div>({{ number_format((float)($tributos['percentual_municipal'] ?? 0),2,',','.') }}%)</div>@else<div class="value">Composição não informada separadamente</div>@endif
</td>
<td class="center" style="width:25%"><div class="bold">TOTAL APROXIMADO</div><div class="tax-value">R$ {{ $money($tributos['total'] ?? 0) }}</div><div>({{ number_format((float)($tributos['percentual_total'] ?? 0),2,',','.') }}%)</div>
@if(!empty($tributos['produto_importado']))<div class="small">Produto de origem importada: tributos anteriores e de importação considerados no total quando informados pelo IBPT/XML.</div>@endif
@if((float)($tributos['ii_destacado_xml'] ?? 0)>0)<div class="small bold">II destacado no XML: R$ {{ $money($tributos['ii_destacado_xml']) }}</div>@endif
</td>
</tr>
</table>
<div class="footer-date">{{ now()->format('d/m/Y H:i:s') }}</div>
</body>
</html>
