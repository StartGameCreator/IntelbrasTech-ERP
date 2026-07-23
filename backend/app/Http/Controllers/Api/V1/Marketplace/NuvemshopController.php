<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\{MarketplaceAccount, MarketplaceConnector, MarketplaceProduto, MarketplacePedido, Produto, ProdutoFornecedor};
use App\Services\Integracao\NuvemshopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, DB, Log};
use Illuminate\Support\Str;
use Throwable;

class NuvemshopController extends Controller
{
    public function __construct(private readonly NuvemshopService $service) {}

    public function status(): JsonResponse
    {
        $account = $this->account(false);
        return response()->json(['conectado'=>$account?->status === 'conectado','account'=>$account]);
    }

    public function redirect(): JsonResponse
    {
        $state = Str::random(48);
        Cache::put('nuvemshop_oauth_'.$state, ['empresa_id'=>auth()->user()->empresa_id], now()->addMinutes(15));
        return response()->json(['url'=>$this->service->authorizationUrl($state)]);
    }

    public function callback(Request $request): JsonResponse
    {
        $data = $request->validate(['code'=>'required|string','state'=>'required|string']);
        $oauth = Cache::pull('nuvemshop_oauth_'.$data['state']);
        abort_unless($oauth && (int)$oauth['empresa_id'] === (int)auth()->user()->empresa_id, 422, 'Estado OAuth inválido ou expirado.');
        $token = $this->service->exchangeCode($data['code']);
        $account = $this->account();
        $account->update([
            'status'=>'conectado','access_token'=>$token['access_token'] ?? null,
            'external_store_id'=>(string)($token['user_id'] ?? ''),
            'nome_conta'=>'Nuvemshop #'.($token['user_id'] ?? ''),
            'configuracoes'=>array_merge($account->configuracoes ?? [], ['store_id'=>$token['user_id'] ?? null]),
        ]);
        $store = $this->service->store($account->fresh());
        $account->update(['nome_conta'=>data_get($store,'name.pt') ?: data_get($store,'name') ?: $account->nome_conta]);
        return response()->json(['message'=>'Nuvemshop conectada com sucesso.','account'=>$account->fresh()]);
    }

    public function test(): JsonResponse
    {
        $store = $this->service->store($this->account());
        return response()->json(['ok'=>true,'store'=>$store]);
    }

    public function syncProducts(): JsonResponse
    {
        $account = $this->account(); $total = 0;
        foreach ($this->service->products($account) as $remote) {
            $sku = (string)(data_get($remote, 'variants.0.sku') ?: 'NS-'.$remote['id']);
            $produto = Produto::where('empresa_id',$account->empresa_id)->where('sku',$sku)->first();
            if (!$produto) {
                $produto = Produto::create([
                    'empresa_id'=>$account->empresa_id,
                    'nome'=>data_get($remote,'name.pt') ?: data_get($remote,'name') ?: 'Produto Nuvemshop '.$remote['id'],
                    'sku'=>$sku,
                    'codigo'=>$sku,
                    'descricao'=>data_get($remote,'description.pt') ?: data_get($remote,'description'),
                    'preco_venda'=>(float)(data_get($remote,'variants.0.price') ?: 0),
                    'preco_custo'=>0,
                    'controla_estoque'=>true,
                    'ativo'=>true,
                    'fonte_importacao'=>'nuvemshop',
                    'importado_em'=>now(),
                ]);
            }
            DB::table('marketplace_products')->updateOrInsert(
                ['empresa_id'=>$account->empresa_id,'account_id'=>$account->id,'external_id'=>(string)$remote['id']],
                ['produto_id'=>$produto->id,'status'=>'sincronizado','preco'=>(float)(data_get($remote,'variants.0.price') ?: 0),'estoque'=>(float)(data_get($remote,'variants.0.stock') ?: 0),'dados'=>json_encode($remote),'sincronizado_em'=>now(),'updated_at'=>now(),'created_at'=>now()]
            ); $total++;
        }
        $account->update(['ultima_sincronizacao_em'=>now()]);
        return response()->json(['message'=>"{$total} produtos importados/atualizados.",'total'=>$total]);
    }

    public function syncOrders(): JsonResponse
    {
        $account = $this->account(); $total = 0; $routed = 0;
        foreach ($this->service->orders($account) as $remote) {
            $orderId = DB::table('marketplace_orders')->updateOrInsert(
                ['account_id'=>$account->id,'external_id'=>(string)$remote['id']],
                ['empresa_id'=>$account->empresa_id,'status'=>(string)($remote['status'] ?? 'open'),'total'=>(float)($remote['total'] ?? 0),'dados'=>json_encode($remote),'importado_em'=>now(),'updated_at'=>now(),'created_at'=>now()]
            );
            $local = DB::table('marketplace_orders')->where('account_id',$account->id)->where('external_id',(string)$remote['id'])->first();
            if ($local) $routed += $this->routeDropship($local->id, $account->empresa_id, $remote);
            $total++;
        }
        return response()->json(['message'=>"{$total} pedidos sincronizados; {$routed} ordens de fornecedor geradas.",'total'=>$total,'ordens_fornecedor'=>$routed]);
    }

    public function webhook(Request $request): JsonResponse
    {
        Log::info('Webhook Nuvemshop', $request->all());
        $storeId = (string)($request->input('store_id') ?: $request->input('user_id'));
        $account = MarketplaceAccount::where('external_store_id',$storeId)->where('status','conectado')->first();
        if (!$account) return response()->json(['received'=>true]);
        DB::table('marketplace_queue')->insert(['empresa_id'=>$account->empresa_id,'account_id'=>$account->id,'tipo'=>'nuvemshop_webhook','status'=>'pendente','payload'=>json_encode($request->all()),'processar_em'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['received'=>true]);
    }

    public function dropshipDashboard(): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;
        return response()->json([
            'resumo'=>[
                'produtos_dropship'=>ProdutoFornecedor::where('dropship_ativo',true)->whereHas('produto',fn($q)=>$q->where('empresa_id',$empresaId))->count(),
                'ordens_pendentes'=>DB::table('dropship_pedidos_fornecedor')->where('empresa_id',$empresaId)->where('status','pendente')->count(),
                'ordens_despachadas'=>DB::table('dropship_pedidos_fornecedor')->where('empresa_id',$empresaId)->where('status','despachado')->count(),
                'custo_pendente'=>(float)DB::table('dropship_pedidos_fornecedor')->where('empresa_id',$empresaId)->where('status','pendente')->sum('total_custo'),
            ],
            'ordens'=>DB::table('dropship_pedidos_fornecedor as d')->join('fornecedores as f','f.id','=','d.fornecedor_id')->where('d.empresa_id',$empresaId)->select('d.*','f.nome as fornecedor_nome')->latest('d.id')->limit(100)->get(),
        ]);
    }

    private function routeDropship(int $marketplaceOrderId, int $empresaId, array $remote): int
    {
        $groups = [];
        foreach (($remote['products'] ?? []) as $item) {
            $sku = (string)($item['sku'] ?? '');
            $produto = $sku !== '' ? Produto::where('empresa_id',$empresaId)->where('sku',$sku)->first() : null;
            if (!$produto) continue;
            $link = ProdutoFornecedor::where('produto_id',$produto->id)->where('dropship_ativo',true)->orderByDesc('principal')->orderBy('custo')->first();
            if (!$link) continue;
            $groups[$link->fornecedor_id][] = compact('item','produto','link');
        }
        foreach ($groups as $fornecedorId=>$items) {
            $order = DB::table('dropship_pedidos_fornecedor')->where('marketplace_order_id',$marketplaceOrderId)->where('fornecedor_id',$fornecedorId)->first();
            if (!$order) {
                $id = DB::table('dropship_pedidos_fornecedor')->insertGetId(['empresa_id'=>$empresaId,'marketplace_order_id'=>$marketplaceOrderId,'fornecedor_id'=>$fornecedorId,'numero'=>'DS-'.str_pad((string)$marketplaceOrderId,8,'0',STR_PAD_LEFT).'-'.$fornecedorId,'status'=>'pendente','created_at'=>now(),'updated_at'=>now()]);
            } else $id = $order->id;
            DB::table('dropship_pedido_itens')->where('pedido_fornecedor_id',$id)->delete(); $cost = 0;
            foreach ($items as $row) {
                $qty=(float)($row['item']['quantity']??1); $unit=(float)$row['link']->custo; $total=$qty*$unit; $cost += $total;
                DB::table('dropship_pedido_itens')->insert(['pedido_fornecedor_id'=>$id,'produto_id'=>$row['produto']->id,'external_product_id'=>(string)($row['item']['product_id']??''),'sku'=>$row['produto']->sku,'descricao'=>$row['produto']->nome,'quantidade'=>$qty,'custo_unitario'=>$unit,'total_custo'=>$total,'dados'=>json_encode($row['item']),'created_at'=>now(),'updated_at'=>now()]);
            }
            DB::table('dropship_pedidos_fornecedor')->where('id',$id)->update(['custo_produtos'=>$cost,'total_custo'=>$cost,'dados'=>json_encode(['cliente'=>$remote['customer']??null,'shipping_address'=>$remote['shipping_address']??null]),'updated_at'=>now()]);
        }
        return count($groups);
    }

    private function account(bool $create = true): ?MarketplaceAccount
    {
        $connector = MarketplaceConnector::firstOrCreate(['slug'=>'nuvemshop'],['nome'=>'Nuvemshop','categoria'=>'ecommerce','descricao'=>'Loja virtual com catálogo, pedidos e dropshipping','ativo'=>true,'ordem'=>34]);
        $query=['empresa_id'=>auth()->user()->empresa_id,'connector_id'=>$connector->id];
        return $create ? MarketplaceAccount::firstOrCreate($query,['status'=>'desconectado']) : MarketplaceAccount::where($query)->first();
    }
}
