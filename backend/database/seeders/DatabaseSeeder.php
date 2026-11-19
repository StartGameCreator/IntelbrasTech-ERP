<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{Hash, DB};
use App\Models\{Empresa, Usuario, Perfil, Permissao, Deposito, UnidadeMedida, OsStatus, ContaBancaria, PlanoConta, FormaPagamento, Pipeline};

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Empresa demo
        $empresa = Empresa::create([
            'razao_social'      => 'IntelbrasTech Soluções Ltda',
            'nome_fantasia'     => 'IntelbrasTech',
            'cnpj'              => '00.000.000/0001-00',
            'email'             => 'contato@intelbrastech.com.br',
            'telefone'          => '(47) 3333-0000',
            'cep'               => '89000-000',
            'cidade'            => 'Blumenau',
            'estado'            => 'SC',
            'regime_tributario' => 1,
            'plano'             => 'enterprise',
            'ativo'             => true,
        ]);

        // 2. Perfil Admin
        $perfil = Perfil::create([
            'empresa_id' => $empresa->id,
            'nome'       => 'Administrador',
            'admin'      => true,
            'ativo'      => true,
        ]);

        // 3. Usuário Admin
        Usuario::create([
            'empresa_id' => $empresa->id,
            'perfil_id'  => $perfil->id,
            'nome'       => 'Administrador',
            'email'      => 'admin@intelbrastech.com.br',
            'senha'      => Hash::make('Admin@123'),
            'cargo'      => 'Administrador do Sistema',
            'tema'       => 'dark',
            'ativo'      => true,
        ]);

        // 3.1 Usuário Master (super-administrador — perfil admin dá bypass total de permissões)
        Usuario::create([
            'empresa_id' => $empresa->id,
            'perfil_id'  => $perfil->id,
            'nome'       => 'Elvis',
            'email'      => 'elvis@intelbrastech.com.br',
            'senha'      => Hash::make('Start@Game357'),
            'cargo'      => 'Administrador Master',
            'tema'       => 'dark',
            'ativo'      => true,
        ]);

        // 4. Permissões padrão
        $permissoes = [
            ['clientes','view'],['clientes','create'],['clientes','edit'],['clientes','delete'],
            ['fornecedores','view'],['fornecedores','create'],['fornecedores','edit'],['fornecedores','delete'],
            ['recibos','view'],['recibos','create'],
            ['integracoes','view'],['integracoes','create'],['integracoes','edit'],['integracoes','delete'],
            ['produtos','view'],['produtos','create'],['produtos','edit'],['produtos','delete'],
            ['pedidos','view'],['pedidos','create'],['pedidos','edit'],['pedidos','delete'],
            ['estoque','view'],['estoque','create'],['estoque','edit'],['estoque','delete'],['estoque','ajustar'],['estoque','transferir'],
            ['financeiro','view'],['financeiro','create'],['financeiro','edit'],
            ['fiscal','view'],['fiscal','emitir'],['fiscal','cancelar'],
            ['compras','view'],['compras','create'],['compras','edit'],['compras','delete'],['compras','receber'],
            ['crm','view'],['crm','create'],['crm','edit'],['crm','delete'],
            ['os','view'],['os','create'],['os','edit'],['os','delete'],
            ['rh','view'],
            ['relatorios','view'],['relatorios','export'],
            ['configuracoes','view'],['configuracoes','edit'],
            ['usuarios','view'],['usuarios','create'],['usuarios','edit'],
        ];
        foreach ($permissoes as [$modulo, $acao]) {
            Permissao::firstOrCreate(['modulo' => $modulo, 'acao' => $acao]);
        }

        // 5. Depósito principal
        Deposito::create([
            'empresa_id' => $empresa->id,
            'nome'       => 'Estoque Principal',
            'cidade'     => 'Blumenau',
            'estado'     => 'SC',
            'principal'  => true,
            'ativo'      => true,
        ]);

        // 6. Unidades de medida
        foreach ([['UN','Unidade'],['KG','Quilograma'],['M','Metro'],['L','Litro'],['CX','Caixa'],['PC','Peça'],['PAR','Par']] as [$sigla, $desc]) {
            UnidadeMedida::firstOrCreate(['empresa_id' => $empresa->id, 'sigla' => $sigla], ['descricao' => $desc]);
        }

        // 7. Status de OS
        $statusOS = [
            ['Aguardando Diagnóstico', '#f59e0b', 1, 'andamento'],
            ['Em Diagnóstico',         '#3b82f6', 2, 'andamento'],
            ['Aguardando Aprovação',   '#8b5cf6', 3, 'aguardando'],
            ['Em Reparo',              '#6366f1', 4, 'andamento'],
            ['Aguardando Peças',       '#f97316', 5, 'aguardando'],
            ['Concluída',              '#22c55e', 6, 'concluido'],
            ['Entregue',               '#10b981', 7, 'entregue'],
            ['Cancelada',              '#ef4444', 8, 'cancelado'],
        ];
        foreach ($statusOS as [$nome, $cor, $ordem, $tipo]) {
            OsStatus::create(['empresa_id' => $empresa->id, 'nome' => $nome, 'cor' => $cor, 'ordem' => $ordem, 'tipo' => $tipo]);
        }

        // 8. Financeiro: conta bancária, plano de contas e formas de pagamento padrão
        $caixa = ContaBancaria::create([
            'empresa_id'    => $empresa->id,
            'nome'          => 'Caixa Principal',
            'tipo'          => 'caixa',
            'saldo_inicial' => 0,
            'saldo_atual'   => 0,
            'principal'     => true,
            'ativo'         => true,
        ]);

        $planoContas = [
            ['Receita de Vendas',        'receita', 'C'],
            ['Receita de Serviços',      'receita', 'C'],
            ['Despesas Operacionais',    'despesa', 'D'],
            ['Despesas com Fornecedores','despesa', 'D'],
            ['Despesas Administrativas', 'despesa', 'D'],
            ['Impostos e Taxas',         'despesa', 'D'],
        ];
        foreach ($planoContas as [$nome, $tipo, $natureza]) {
            PlanoConta::create(['empresa_id' => $empresa->id, 'nome' => $nome, 'tipo' => $tipo, 'natureza' => $natureza]);
        }

        $formasPagamento = [
            ['Dinheiro',          'dinheiro', 0, 0],
            ['PIX',               'pix', 0, 0],
            ['Boleto Bancário',   'boleto', 0, 1],
            ['Cartão de Crédito', 'cartao_credito', 3.5, 30],
            ['Cartão de Débito',  'cartao_debito', 1.5, 1],
        ];
        foreach ($formasPagamento as [$nome, $tipo, $taxa, $prazo]) {
            FormaPagamento::create([
                'empresa_id' => $empresa->id, 'nome' => $nome, 'tipo' => $tipo,
                'taxa' => $taxa, 'prazo_recebimento_dias' => $prazo, 'conta_bancaria_id' => $caixa->id,
            ]);
        }

        // 9. CRM: pipeline padrão de vendas com etapas
        $pipeline = Pipeline::create(['empresa_id' => $empresa->id, 'nome' => 'Funil de Vendas']);
        $etapas = [
            ['Novo Lead',          '#94a3b8'],
            ['Contato Feito',      '#3b82f6'],
            ['Proposta Enviada',   '#f59e0b'],
            ['Negociação',         '#8b5cf6'],
        ];
        foreach ($etapas as $i => [$nome, $cor]) {
            $pipeline->etapas()->create(['nome' => $nome, 'cor' => $cor, 'ordem' => $i]);
        }

        $this->command->info('✅ Seed concluído!');
        $this->command->info('   Login admin: admin@intelbrastech.com.br / Admin@123');
        $this->command->info('   Login master: elvis@intelbrastech.com.br / Start@Game357');
    }
}
