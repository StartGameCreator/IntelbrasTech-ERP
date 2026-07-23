<?php

namespace App\Services\Expedicao;

use App\Models\Pedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ExpedicaoService
{
    public function criarParaPedido(Pedido $pedido): object
    {
        return DB::transaction(function () use ($pedido) {
            $existente = DB::table('expedicoes')
                ->where('empresa_id', $pedido->empresa_id)
                ->where('pedido_id', $pedido->id)
                ->first();

            if ($existente) {
                return $existente;
            }

            $codigo = 'EXP-' . now()->format('Ymd') . '-' . str_pad((string) $pedido->id, 6, '0', STR_PAD_LEFT);

            $expedicaoId = DB::table('expedicoes')->insertGetId([
                'empresa_id' => $pedido->empresa_id,
                'pedido_id' => $pedido->id,
                'codigo' => $codigo,
                'status' => 'aguardando_separacao',
                'total_itens' => $pedido->itens()->count(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($pedido->itens as $item) {
                DB::table('expedicao_itens')->insert([
                    'expedicao_id' => $expedicaoId,
                    'pedido_item_id' => $item->id,
                    'produto_id' => $item->produto_id,
                    'codigo' => $item->codigo,
                    'descricao' => $item->descricao,
                    'quantidade' => $item->quantidade,
                    'status' => 'pendente',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->evento($expedicaoId, 'criada', 'Expedição criada a partir do pedido.');

            return DB::table('expedicoes')->where('id', $expedicaoId)->first();
        });
    }

    public function iniciarSeparacao(int $empresaId, int $id, int $usuarioId): void
    {
        $expedicao = $this->expedicao($empresaId, $id);

        if (!in_array($expedicao->status, ['aguardando_separacao','separando'], true)) {
            throw new RuntimeException('A expedição não pode iniciar separação neste status.');
        }

        DB::table('expedicoes')->where('id', $id)->update([
            'status' => 'separando',
            'separador_id' => $usuarioId,
            'separacao_iniciada_em' => $expedicao->separacao_iniciada_em ?: now(),
            'updated_at' => now(),
        ]);

        $this->evento($id, 'separacao_iniciada', 'Separação iniciada.', $usuarioId);
    }

    public function separarItem(int $empresaId, int $expedicaoId, int $itemId, float $quantidade, int $usuarioId): void
    {
        $this->expedicao($empresaId, $expedicaoId);

        $item = DB::table('expedicao_itens')
            ->where('id', $itemId)
            ->where('expedicao_id', $expedicaoId)
            ->first();

        if (!$item) {
            throw new RuntimeException('Item da expedição não encontrado.');
        }

        $novaQuantidade = min((float) $item->quantidade, max(0, $quantidade));
        $status = $novaQuantidade >= (float) $item->quantidade ? 'separado' : 'parcial';

        DB::table('expedicao_itens')->where('id', $itemId)->update([
            'quantidade_separada' => $novaQuantidade,
            'status' => $status,
            'updated_at' => now(),
        ]);

        $this->recalcular($expedicaoId);
        $this->evento($expedicaoId, 'item_separado', "Item {$item->descricao} separado.", $usuarioId, [
            'item_id' => $itemId,
            'quantidade' => $novaQuantidade,
        ]);
    }

    public function concluirSeparacao(int $empresaId, int $id, int $usuarioId): void
    {
        $this->expedicao($empresaId, $id);

        $pendentes = DB::table('expedicao_itens')
            ->where('expedicao_id', $id)
            ->whereColumn('quantidade_separada', '<', 'quantidade')
            ->count();

        if ($pendentes > 0) {
            throw new RuntimeException('Ainda existem itens não separados.');
        }

        DB::table('expedicoes')->where('id', $id)->update([
            'status' => 'aguardando_conferencia',
            'separacao_concluida_em' => now(),
            'updated_at' => now(),
        ]);

        $this->evento($id, 'separacao_concluida', 'Separação concluída.', $usuarioId);
    }

    public function iniciarConferencia(int $empresaId, int $id, int $usuarioId): void
    {
        $expedicao = $this->expedicao($empresaId, $id);

        if (!in_array($expedicao->status, ['aguardando_conferencia','conferindo'], true)) {
            throw new RuntimeException('A expedição não pode iniciar conferência neste status.');
        }

        DB::table('expedicoes')->where('id', $id)->update([
            'status' => 'conferindo',
            'conferente_id' => $usuarioId,
            'conferencia_iniciada_em' => $expedicao->conferencia_iniciada_em ?: now(),
            'updated_at' => now(),
        ]);

        $this->evento($id, 'conferencia_iniciada', 'Conferência iniciada.', $usuarioId);
    }

    public function conferirItem(int $empresaId, int $expedicaoId, int $itemId, float $quantidade, int $usuarioId): void
    {
        $this->expedicao($empresaId, $expedicaoId);

        $item = DB::table('expedicao_itens')
            ->where('id', $itemId)
            ->where('expedicao_id', $expedicaoId)
            ->first();

        if (!$item) {
            throw new RuntimeException('Item da expedição não encontrado.');
        }

        $novaQuantidade = min((float) $item->quantidade, max(0, $quantidade));
        $status = $novaQuantidade >= (float) $item->quantidade ? 'conferido' : 'divergente';

        DB::table('expedicao_itens')->where('id', $itemId)->update([
            'quantidade_conferida' => $novaQuantidade,
            'status' => $status,
            'updated_at' => now(),
        ]);

        $this->recalcular($expedicaoId);
        $this->evento($expedicaoId, 'item_conferido', "Item {$item->descricao} conferido.", $usuarioId, [
            'item_id' => $itemId,
            'quantidade' => $novaQuantidade,
        ]);
    }

    public function concluirConferencia(int $empresaId, int $id, int $usuarioId): void
    {
        $this->expedicao($empresaId, $id);

        $divergentes = DB::table('expedicao_itens')
            ->where('expedicao_id', $id)
            ->whereColumn('quantidade_conferida', '<', 'quantidade')
            ->count();

        if ($divergentes > 0) {
            throw new RuntimeException('Ainda existem itens com divergência na conferência.');
        }

        DB::table('expedicoes')->where('id', $id)->update([
            'status' => 'aguardando_embalagem',
            'conferencia_concluida_em' => now(),
            'updated_at' => now(),
        ]);

        $this->evento($id, 'conferencia_concluida', 'Conferência concluída.', $usuarioId);
    }

    public function criarVolume(int $empresaId, int $id, array $dados, int $usuarioId): object
    {
        $this->expedicao($empresaId, $id);

        $numero = (int) (DB::table('expedicao_volumes')
            ->where('expedicao_id', $id)
            ->max('numero') ?? 0) + 1;

        $codigo = 'VOL-' . str_pad((string) $id, 8, '0', STR_PAD_LEFT) . '-' . str_pad((string) $numero, 2, '0', STR_PAD_LEFT);

        $volumeId = DB::table('expedicao_volumes')->insertGetId([
            'expedicao_id' => $id,
            'numero' => $numero,
            'codigo_barras' => $codigo,
            'qr_code' => $codigo,
            'peso' => $dados['peso'] ?? null,
            'altura' => $dados['altura'] ?? null,
            'largura' => $dados['largura'] ?? null,
            'comprimento' => $dados['comprimento'] ?? null,
            'status' => 'fechado',
            'observacoes' => $dados['observacoes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('expedicoes')->where('id', $id)->update([
            'status' => 'pronto_expedicao',
            'embalador_id' => $usuarioId,
            'total_volumes' => DB::table('expedicao_volumes')->where('expedicao_id', $id)->count(),
            'updated_at' => now(),
        ]);

        $this->evento($id, 'volume_criado', "Volume {$numero} criado.", $usuarioId);

        return DB::table('expedicao_volumes')->where('id', $volumeId)->first();
    }

    public function expedir(int $empresaId, int $id, int $usuarioId): void
    {
        $expedicao = $this->expedicao($empresaId, $id);

        if ((int) $expedicao->total_volumes < 1) {
            throw new RuntimeException('Crie pelo menos um volume antes de expedir.');
        }

        DB::table('expedicoes')->where('id', $id)->update([
            'status' => 'expedida',
            'expedida_em' => now(),
            'updated_at' => now(),
        ]);

        DB::table('pedidos')->where('id', $expedicao->pedido_id)->update([
            'status_entrega' => 'expedido',
            'updated_at' => now(),
        ]);

        $this->evento($id, 'expedida', 'Expedição finalizada e liberada para transporte.', $usuarioId);
    }

    private function recalcular(int $id): void
    {
        $itens = DB::table('expedicao_itens')->where('expedicao_id', $id)->get();

        DB::table('expedicoes')->where('id', $id)->update([
            'itens_separados' => $itens->filter(fn ($i) => (float) $i->quantidade_separada >= (float) $i->quantidade)->count(),
            'itens_conferidos' => $itens->filter(fn ($i) => (float) $i->quantidade_conferida >= (float) $i->quantidade)->count(),
            'updated_at' => now(),
        ]);
    }

    private function expedicao(int $empresaId, int $id): object
    {
        $expedicao = DB::table('expedicoes')
            ->where('id', $id)
            ->where('empresa_id', $empresaId)
            ->first();

        if (!$expedicao) {
            throw new RuntimeException('Expedição não encontrada.');
        }

        return $expedicao;
    }

    private function evento(int $expedicaoId, string $tipo, string $descricao, ?int $usuarioId = null, ?array $dados = null): void
    {
        DB::table('expedicao_eventos')->insert([
            'expedicao_id' => $expedicaoId,
            'usuario_id' => $usuarioId ?: auth()->id(),
            'tipo' => $tipo,
            'descricao' => $descricao,
            'dados' => $dados ? json_encode($dados, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
        ]);
    }
}
