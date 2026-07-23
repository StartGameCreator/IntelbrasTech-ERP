<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Romaneio {{ $expedicao->codigo }}</title>
<style>
body{font-family:Arial,sans-serif;color:#111;margin:28px}
h1{font-size:20px;margin:0 0 4px}
small{color:#555}
table{width:100%;border-collapse:collapse;margin-top:20px}
th,td{border:1px solid #bbb;padding:8px;font-size:12px;text-align:left}
th{background:#eee}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:18px}
.box{border:1px solid #bbb;padding:10px}
.code{font-family:monospace;font-size:16px;font-weight:bold}
@media print{button{display:none}}
</style>
</head>
<body>
<button onclick="window.print()">Imprimir</button>
<h1>Romaneio de Expedição</h1>
<small>{{ $expedicao->codigo }}</small>

<div class="grid">
  <div class="box"><small>Pedido</small><div>#{{ $expedicao->pedido_numero }}</div></div>
  <div class="box"><small>Canal</small><div>{{ $expedicao->canal }}</div></div>
  <div class="box"><small>Status</small><div>{{ $expedicao->status }}</div></div>
  <div class="box"><small>Volumes</small><div>{{ $expedicao->total_volumes }}</div></div>
</div>

<table>
<thead><tr><th>Código</th><th>Descrição</th><th>Qtd.</th><th>Separado</th><th>Conferido</th></tr></thead>
<tbody>
@foreach($itens as $item)
<tr>
<td>{{ $item->codigo }}</td>
<td>{{ $item->descricao }}</td>
<td>{{ $item->quantidade }}</td>
<td>{{ $item->quantidade_separada }}</td>
<td>{{ $item->quantidade_conferida }}</td>
</tr>
@endforeach
</tbody>
</table>

<h2>Volumes</h2>
@foreach($volumes as $volume)
<div class="box" style="margin-top:8px">
  <div>Volume {{ $volume->numero }}</div>
  <div class="code">{{ $volume->codigo_barras }}</div>
  <small>Peso: {{ $volume->peso ?? '—' }} kg | Dimensões: {{ $volume->altura ?? '—' }} × {{ $volume->largura ?? '—' }} × {{ $volume->comprimento ?? '—' }} cm</small>
</div>
@endforeach
</body>
</html>
