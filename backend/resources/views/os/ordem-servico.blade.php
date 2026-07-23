<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Ordem de Serviço {{ $os->numero }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #222; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 12px; }
        .header h1 { font-size: 16px; margin: 0; }
        .header .numero { font-size: 14px; font-weight: bold; }
        .section { margin-bottom: 14px; }
        .section h2 { font-size: 12px; text-transform: uppercase; background: #f0f0f0; padding: 4px 6px; margin: 0 0 6px; }
        .grid { display: flex; flex-wrap: wrap; }
        .grid .col { width: 33%; margin-bottom: 6px; }
        .grid .col strong { display: block; font-size: 9px; color: #666; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table th, table td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; font-size: 10px; }
        table th { background: #f5f5f5; }
        .assinaturas { display: flex; justify-content: space-between; margin-top: 40px; }
        .assinaturas div { width: 45%; border-top: 1px solid #333; text-align: center; padding-top: 4px; font-size: 10px; }
        .total { text-align: right; font-size: 13px; font-weight: bold; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>{{ $empresa->nome_fantasia ?? $empresa->razao_social }}</h1>
            <div>{{ $empresa->cnpj }}</div>
        </div>
        <div class="numero">Ordem de Serviço {{ $os->numero }}</div>
    </div>

    <div class="section">
        <h2>Cliente</h2>
        <div class="grid">
            <div class="col"><strong>Nome</strong>{{ $cliente->razao_social ?? $cliente->nome_fantasia ?? '—' }}</div>
            <div class="col"><strong>Telefone</strong>{{ $cliente->telefone ?? $cliente->celular ?? '—' }}</div>
            <div class="col"><strong>Data de Entrada</strong>{{ $os->data_entrada?->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    <div class="section">
        <h2>Equipamento</h2>
        <div class="grid">
            <div class="col"><strong>Tipo</strong>{{ $os->equipamento_tipo ?? '—' }}</div>
            <div class="col"><strong>Marca</strong>{{ $os->equipamento_marca ?? '—' }}</div>
            <div class="col"><strong>Modelo</strong>{{ $os->equipamento_modelo ?? '—' }}</div>
            <div class="col"><strong>Número de Série</strong>{{ $os->equipamento_serie ?? '—' }}</div>
            <div class="col"><strong>Em Garantia</strong>{{ $os->em_garantia ? 'Sim' : 'Não' }}</div>
            <div class="col"><strong>Técnico Responsável</strong>{{ $os->tecnico?->nome ?? '—' }}</div>
        </div>
    </div>

    <div class="section">
        <h2>Defeito e Diagnóstico</h2>
        <p><strong>Defeito Relatado:</strong> {{ $os->defeito_relatado ?? '—' }}</p>
        <p><strong>Defeito Constatado:</strong> {{ $os->defeito_constatado ?? '—' }}</p>
        <p><strong>Laudo Técnico:</strong> {{ $os->laudo_tecnico ?? '—' }}</p>
        <p><strong>Solução Aplicada:</strong> {{ $os->solucao ?? '—' }}</p>
    </div>

    @if($pecas->count())
    <div class="section">
        <h2>Peças Utilizadas</h2>
        <table>
            <thead><tr><th>Descrição</th><th>Qtd.</th><th>Preço Unit.</th><th>Total</th></tr></thead>
            <tbody>
                @foreach($pecas as $peca)
                <tr>
                    <td>{{ $peca->descricao ?? $peca->produto?->nome }}</td>
                    <td>{{ number_format($peca->quantidade, 3, ',', '.') }}</td>
                    <td>R$ {{ number_format($peca->preco_unitario, 2, ',', '.') }}</td>
                    <td>R$ {{ number_format($peca->total, 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="section">
        <div class="grid">
            <div class="col"><strong>Valor do Serviço</strong>R$ {{ number_format($os->valor_servico, 2, ',', '.') }}</div>
            <div class="col"><strong>Desconto</strong>R$ {{ number_format($os->desconto, 2, ',', '.') }}</div>
            <div class="col"><strong>Total Geral</strong>R$ {{ number_format($os->total_geral, 2, ',', '.') }}</div>
        </div>
    </div>

    <div class="assinaturas">
        <div>Assinatura do Cliente</div>
        <div>Assinatura do Técnico Responsável</div>
    </div>
</body>
</html>
