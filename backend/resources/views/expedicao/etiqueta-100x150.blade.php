<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
@page { margin: 5mm; }
* { box-sizing: border-box; }
body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #000; font-size: 10px; }
.box { border: 1.5px solid #000; width: 100%; min-height: 138mm; padding: 3mm; }
.row { display: table; width: 100%; table-layout: fixed; }
.col { display: table-cell; vertical-align: top; }
.right { text-align: right; }
.center { text-align: center; }
.marketplace { font-size: 16px; font-weight: bold; text-transform: uppercase; }
.service { font-size: 11px; font-weight: bold; margin-top: 2mm; }
.divider { border-top: 1px solid #000; margin: 3mm 0; }
.label { font-size: 8px; text-transform: uppercase; }
.recipient { font-size: 15px; font-weight: bold; line-height: 1.25; margin-top: 1mm; }
.address { font-size: 12px; line-height: 1.35; margin-top: 2mm; }
.cep { font-size: 18px; font-weight: bold; margin-top: 2mm; }
.tracking { font-size: 17px; font-weight: bold; letter-spacing: 1px; word-break: break-all; }
.barcode { height: 23mm; margin: 3mm 0 1mm; border-top: 10px repeating-linear-gradient(90deg,#000 0,#000 1px,#fff 1px,#fff 3px); }
.small { font-size: 8px; }
.volume { font-size: 14px; font-weight: bold; border: 1px solid #000; padding: 2mm; display: inline-block; }
</style>
</head>
<body>
<div class="box">
    <div class="row">
        <div class="col">
            <div class="marketplace">{{ $marketplace ?: 'VENDA DIRETA' }}</div>
            <div class="service">{{ $transportadora ?: 'TRANSPORTADORA' }} {{ $servico ? '— '.$servico : '' }}</div>
        </div>
        <div class="col right">
            <div class="volume">VOLUME {{ $volume_numero }}/{{ $total_volumes }}</div>
            <div class="small" style="margin-top:2mm">Pedido #{{ $pedido->numero }}</div>
        </div>
    </div>

    <div class="divider"></div>

    <div class="label">ENTREGAR PARA</div>
    <div class="recipient">{{ $destinatario['nome'] ?: 'CONSUMIDOR' }}</div>
    <div class="address">
        {{ $destinatario['logradouro'] }}, {{ $destinatario['numero'] }}
        {{ $destinatario['complemento'] ? ' — '.$destinatario['complemento'] : '' }}<br>
        {{ $destinatario['bairro'] }}<br>
        {{ $destinatario['cidade'] }} — {{ $destinatario['uf'] }}
    </div>
    <div class="cep">CEP {{ $destinatario['cep'] }}</div>

    <div class="divider"></div>

    <div class="label">CÓDIGO DE RASTREAMENTO</div>
    <div class="tracking">{{ $codigo_rastreio ?: 'AGUARDANDO RASTREIO' }}</div>
    <div class="barcode"></div>
    <div class="center small">{{ $codigo_rastreio ?: $expedicao->codigo }}</div>

    <div class="divider"></div>

    <div class="row">
        <div class="col">
            <div class="label">EXPEDIÇÃO</div>
            <strong>{{ $expedicao->codigo }}</strong>
        </div>
        <div class="col right">
            <div class="label">EMISSÃO</div>
            <strong>{{ now()->format('d/m/Y H:i') }}</strong>
        </div>
    </div>
</div>
</body>
</html>
