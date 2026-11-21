<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 32px 36px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #17251e; font-size: 10px; line-height: 1.55; }
        .header { border-bottom: 3px solid #00a335; padding-bottom: 14px; margin-bottom: 18px; }
        .brand { font-size: 18px; font-weight: bold; color: #007d2b; }
        .muted { color: #5b6c61; }
        h1 { font-size: 16px; margin: 22px 0 4px; color: #10291b; }
        h2 { font-size: 11px; margin: 18px 0 6px; color: #007d2b; text-transform: uppercase; }
        .box { background: #f2f8f3; border: 1px solid #d2e5d6; padding: 10px; margin: 10px 0; }
        .grid { width: 100%; border-collapse: collapse; margin: 8px 0 15px; }
        .grid td { width: 50%; padding: 6px 8px; border: 1px solid #dfe8e1; vertical-align: top; }
        .label { display: block; color: #5b6c61; font-size: 8px; text-transform: uppercase; letter-spacing: .4px; }
        .value { font-weight: bold; }
        .footer { position: fixed; bottom: -12px; left: 0; right: 0; color: #65756a; font-size: 8px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ $empresa->nome_fantasia ?: $empresa->razao_social }}</div>
        <div class="muted">Termo de prestação de serviços · {{ $contrato->numero }}</div>
    </div>

    <h1>{{ $contrato->titulo }}</h1>
    <p>Este termo registra a contratação relacionada ao chamado <strong>{{ $chamado->protocolo }}</strong>, aberta em {{ $chamado->created_at->format('d/m/Y H:i') }}.</p>

    <h2>Partes</h2>
    <table class="grid"><tr>
        <td><span class="label">Contratada</span><span class="value">{{ $empresa->razao_social ?: $empresa->nome_fantasia }}</span><br>{{ $empresa->cnpj ? 'CNPJ: '.$empresa->cnpj : '' }}<br>{{ $empresa->email }}</td>
        <td><span class="label">Contratante</span><span class="value">{{ $cliente?->razao_social ?: $chamado->nome }}</span><br>{{ $cliente?->cpf_cnpj ? 'CPF/CNPJ: '.$cliente->cpf_cnpj : '' }}<br>{{ $chamado->email ?: $chamado->telefone }}</td>
    </tr></table>

    <h2>Objeto e escopo</h2>
    <div class="box"><strong>Demanda:</strong> {{ $chamado->tipo_demanda }}<br><strong>Relato do cliente:</strong> {{ $chamado->descricao }}<br><br>{!! nl2br(e($contrato->objeto)) !!}</div>

    <h2>Condições comerciais</h2>
    <table class="grid"><tr>
        <td><span class="label">Valor aprovado</span><span class="value">R$ {{ number_format((float) $contrato->valor, 2, ',', '.') }}</span></td>
        <td><span class="label">Validade do orçamento</span><span class="value">{{ $contrato->validade_orcamento?->format('d/m/Y') ?: 'Conforme proposta' }}</span></td>
    </tr></table>
    <p>Os detalhes operacionais, prazo de execução, forma de pagamento, materiais e eventuais garantias serão aqueles descritos na proposta/orçamento apresentado para este chamado. Serviços ou itens não previstos no escopo aprovado dependem de nova autorização.</p>

    <h2>Aceite eletrônico</h2>
    <p>O contratante confirmou eletronicamente a autorização do orçamento em <strong>{{ $contrato->aceito_em?->format('d/m/Y H:i:s') }}</strong>, identificado como <strong>{{ $contrato->aceite_nome }}</strong>. O sistema registra data, hora, protocolo e evidências técnicas de acesso para rastreabilidade.</p>
    <p class="muted">Este documento é um termo operacional gerado pelo ERP. A empresa deve submeter o modelo à validação jurídica antes de utilizá-lo como contrato definitivo para sua atividade.</p>

    <div class="footer">{{ $empresa->nome_fantasia ?: $empresa->razao_social }} · Contrato {{ $contrato->numero }} · Gerado em {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
