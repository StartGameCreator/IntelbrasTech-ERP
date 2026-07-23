<?php

namespace App\Http\Controllers\Api\V1\Configuracoes;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EmpresaController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->empresa]);
    }

    public function update(Request $request): JsonResponse
    {
        $empresa = $request->user()->empresa;

        $request->merge([
            'cnpj' => filled($request->input('cnpj'))
                ? preg_replace('/\D/', '', (string) $request->input('cnpj'))
                : null,
            'cep' => filled($request->input('cep'))
                ? preg_replace('/\D/', '', (string) $request->input('cep'))
                : null,
            'estado' => filled($request->input('estado'))
                ? strtoupper((string) $request->input('estado'))
                : null,
        ]);

        $dados = $request->validate([
            'razao_social' => ['required', 'string', 'max:200'],
            'nome_fantasia' => ['nullable', 'string', 'max:200'],
            'tipo_pessoa' => ['required', Rule::in(['juridica', 'fisica'])],
            'cnpj' => ['nullable', 'string', 'size:14', Rule::unique('empresas', 'cnpj')->ignore($empresa->id)],
            'ie' => ['nullable', 'string', 'max:30'],
            'ie_isento' => ['boolean'],
            'im' => ['nullable', 'string', 'max:30'],
            'cnae' => ['nullable', 'string', 'max:20'],
            'atividade_principal' => ['nullable', 'string', 'max:150'],
            'regime_tributario' => ['required', 'integer', Rule::in([1, 2, 3])],
            'porte_empresa' => ['nullable', 'string', 'max:40'],
            'faturamento_anual_faixa' => ['nullable', 'string', 'max:60'],
            'quantidade_funcionarios' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'segmentos' => ['nullable', 'array'],
            'segmentos.*' => ['string', Rule::in(['comercio', 'ecommerce', 'industria', 'servicos'])],
            'cep' => ['nullable', 'string', 'size:8'],
            'estado' => ['nullable', 'string', 'size:2'],
            'cidade' => ['nullable', 'string', 'max:100'],
            'bairro' => ['nullable', 'string', 'max:100'],
            'logradouro' => ['nullable', 'string', 'max:200'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:100'],
            'ibge' => ['nullable', 'string', 'max:10'],
            'contato_nome' => ['nullable', 'string', 'max:120'],
            'telefone' => ['nullable', 'string', 'max:25'],
            'celular' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'email', 'max:150'],
            'site' => ['nullable', 'string', 'max:200'],
            'inscricoes_substitutas' => ['nullable', 'array'],
            'inscricoes_substitutas.*.uf' => ['required_with:inscricoes_substitutas', 'string', 'size:2'],
            'inscricoes_substitutas.*.inscricao_estadual' => ['required_with:inscricoes_substitutas', 'string', 'max:30'],
        ], [
            'cnpj.unique' => 'Este CNPJ ja esta cadastrado em outra empresa.',
            'cnpj.size' => 'Informe um CNPJ com 14 digitos.',
            'cep.size' => 'Informe um CEP com 8 digitos.',
        ]);

        $dados['ie_isento'] = (bool) ($dados['ie_isento'] ?? false);
        if ($dados['ie_isento']) {
            $dados['ie'] = null;
        }

        if ($dados['cnpj']) {
            $dados['cadastro_pendente'] = false;
            $dados['cadastro_concluido_em'] = now();
        } else {
            $dados['cadastro_pendente'] = true;
            $dados['cadastro_concluido_em'] = null;
        }

        $empresa->fill($dados)->save();

        return response()->json([
            'message' => 'Dados da empresa atualizados com sucesso.',
            'data' => $empresa->fresh(),
        ]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:4096'],
        ]);

        $empresa = $request->user()->empresa;
        if ($empresa->logo && Storage::disk('public')->exists($empresa->logo)) {
            Storage::disk('public')->delete($empresa->logo);
        }

        $empresa->logo = $request->file('logo')->store('empresas/logos', 'public');
        $empresa->save();

        return response()->json([
            'message' => 'Logo atualizado com sucesso.',
            'data' => $empresa->fresh(),
        ]);
    }

    public function removerLogo(Request $request): JsonResponse
    {
        $empresa = $request->user()->empresa;
        if ($empresa->logo && Storage::disk('public')->exists($empresa->logo)) {
            Storage::disk('public')->delete($empresa->logo);
        }

        $empresa->logo = null;
        $empresa->save();

        return response()->json(['message' => 'Logo removido com sucesso.']);
    }
}
