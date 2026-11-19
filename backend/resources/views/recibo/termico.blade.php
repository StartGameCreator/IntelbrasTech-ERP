<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Recibo {{ $recibo->numero }}</title>
    <style>
        @page { margin: 4mm; }
        body { font-family: 'Courier New', monospace; font-size: 11px; color: #000; width: 72mm; }
        .center { text-align: center; }
        .linha { border-top: 1px dashed #000; margin: 6px 0; }
        .linha-dupla { border-top: 2px solid #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { font-size: 10px; padding: 1px 0; vertical-align: top; }
        .total-row td { font-size: 13px; font-weight: bold; padding-top: 4px; }
        .aviso { font-size: 9px; text-align: center; margin-top: 8px; border: 1px solid #000; padding: 4px; }
        .bold { font-weight: bold; }
        h1 { font-size: 13px; margin: 0; }
    </style>
</head>
<body>
    <div class="center">
        <h1>{{ $empresa->nome_fantasia ?? $empresa->razao_social }}</h1>
        @if($empresa->cnpj)<div>CNPJ: {{ $empresa->cnpj }}</div>@endif
        @if($empresa->telefone)<div>{{ $empresa->telefone }}</div>@endif
    </div>

    <div class="linha-dupla"></div>
    <div class="center bold">COMPROVANTE DE VENDA</div>
    <div class="center">Nº {{ $recibo->numero }}</div>
    <div class="center">{{ $recibo->data_emissao->format('d/m/Y H:i') }}</div>
    <div class="linha"></div>

    @if($recibo->cliente_nome)
        <div>Cliente: {{ $recibo->cliente_nome }}</div>
        @if($recibo->cliente_documento)<div>CPF/CNPJ: {{ $recibo->cliente_documento }}</div>@endif
        @if($recibo->cliente_telefone)<div>Tel: {{ $recibo->cliente_telefone }}</div>@endif
        <div class="linha"></div>
    @endif

    <table>
        @foreach($recibo->itens_snapshot as $item)
        <tr>
            <td colspan="3">{{ $item['descricao'] ?? $item['nome'] ?? 'Item' }}</td>
        </tr>
        <tr>
            <td>{{ number_format($item['quantidade'] ?? 1, 2, ',', '.') }} x {{ number_format($item['preco_unitario'] ?? 0, 2, ',', '.') }}</td>
            <td></td>
            <td style="text-align:right">R$ {{ number_format($item['total'] ?? 0, 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </table>

    <div class="linha"></div>
    <table>
        <tr class="total-row">
            <td>TOTAL</td>
            <td style="text-align:right">R$ {{ number_format($recibo->valor_total, 2, ',', '.') }}</td>
        </tr>
    </table>
    @if($recibo->forma_pagamento)
        <div>Pagamento: {{ $recibo->forma_pagamento }}</div>
    @endif

    @if($recibo->prazo_garantia_dias)
        <div class="linha"></div>
        <div class="bold">GARANTIA</div>
        <div>{{ $recibo->prazo_garantia_dias }} dias a partir da compra</div>
        @if($recibo->data_garantia_vence)
            <div>Válida até: {{ \Carbon\Carbon::parse($recibo->data_garantia_vence)->format('d/m/Y') }}</div>
        @endif
    @endif

    @if($recibo->tem_entregador)
        <div class="linha"></div>
        <div class="bold">ENTREGA</div>
        <div>Entregador: {{ $recibo->entregador_nome }}</div>
        @if($recibo->entregador_app_nome)<div>App: {{ $recibo->entregador_app_nome }}</div>@endif
        @if($recibo->entregador_documento)<div>Doc: {{ $recibo->entregador_documento }}</div>@endif
        @if($recibo->veiculo_placa)<div>Placa: {{ $recibo->veiculo_placa }} {{ $recibo->veiculo_modelo }}</div>@endif
    @endif

    @if($recibo->observacoes)
        <div class="linha"></div>
        <div>{{ $recibo->observacoes }}</div>
    @endif

    <div class="aviso">
        ESTE DOCUMENTO NÃO TEM VALOR FISCAL.<br>
        Serve apenas como comprovante de compra para controle de garantia
        e conferência de entrega. Não substitui nota fiscal.
    </div>
</body>
</html>
