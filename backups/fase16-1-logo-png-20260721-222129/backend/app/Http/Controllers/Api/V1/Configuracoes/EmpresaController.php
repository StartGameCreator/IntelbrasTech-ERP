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
        $empresa = $request->user()->empresa->fresh();

        return response()->json([
            'data' => $empresa,
        ]);
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
            'regime_apuracao' => ['required', Rule::in([
                'mei',
                'simples_nacional',
                'simples_excesso',
                'lucro_presumido',
                'lucro_real',
                'arbitrado',
            ])],
            'regime_fiscal_confirmado' => ['accepted'],
            'icms_csosn_padrao' => ['nullable', 'string', Rule::in(['101','102','103','300','400','500','900'])],
            'icms_cst_padrao' => ['nullable', 'string', Rule::in(['00','10','20','30','40','41','50','51','60','70','90'])],
            'pis_cst_padrao' => ['nullable', 'string', 'size:2'],
            'cofins_cst_padrao' => ['nullable', 'string', 'size:2'],
            'fiscal_observacao' => ['nullable', 'string', 'max:1000'],
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

                $dados['regime_tributario'] = match ($dados['regime_apuracao']) {
            'mei', 'simples_nacional' => 1,
            'simples_excesso' => 2,
            'lucro_presumido', 'lucro_real', 'arbitrado' => 3,
        };

        if ($dados['regime_apuracao'] === 'mei') {
            // Perfil inicial definido a partir da consulta oficial do SIMEI:
            // CRT 1, CSOSN 102, PIS 49 e COFINS 49.
            // O operador poderá trocar o CSOSN para 400 pela própria tela.
            $dados['icms_csosn_padrao'] = filled($dados['icms_csosn_padrao'] ?? null)
                ? (string) $dados['icms_csosn_padrao']
                : '102';
            $dados['pis_cst_padrao'] = filled($dados['pis_cst_padrao'] ?? null)
                ? (string) $dados['pis_cst_padrao']
                : '49';
            $dados['cofins_cst_padrao'] = filled($dados['cofins_cst_padrao'] ?? null)
                ? (string) $dados['cofins_cst_padrao']
                : '49';
        }

        if (in_array($dados['regime_apuracao'], ['mei', 'simples_nacional', 'simples_excesso'], true)) {
            $dados['icms_cst_padrao'] = null;

            if (blank($dados['icms_csosn_padrao'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'icms_csosn_padrao' => 'Informe o CSOSN padrão para o Simples Nacional.',
                ]);
            }
        } else {
            $dados['icms_csosn_padrao'] = null;

            if (blank($dados['icms_cst_padrao'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'icms_cst_padrao' => 'Informe o CST de ICMS padrão para o Regime Normal.',
                ]);
            }
        }

        $dados['regime_fiscal_confirmado'] = true;
        $dados['regime_fiscal_confirmado_em'] = now();
        $empresa->fill($dados)->save();

        return response()->json([
            'message' => 'Dados da empresa atualizados com sucesso.',
            'data' => $empresa->fresh(),
        ]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => [
                'required',
                'file',
                'mimes:png,jpg,jpeg,webp',
                'mimetypes:image/png,image/jpeg,image/webp',
                'max:4096',
                'dimensions:min_width=80,min_height=40,max_width=4000,max_height=4000',
            ],
        ], [
            'logo.mimes' => 'Use uma imagem PNG, JPG, JPEG ou WEBP.',
            'logo.mimetypes' => 'O arquivo selecionado não é uma imagem válida.',
            'logo.max' => 'A logo deve possuir no máximo 4 MB.',
            'logo.dimensions' => 'A logo deve ter entre 80x40 e 4000x4000 pixels.',
        ]);

        $empresa = $request->user()->empresa;
        $arquivo = $request->file('logo');

        $novoCaminho = $arquivo->storePubliclyAs(
            'empresas/logos/' . $empresa->id,
            'logo-' . now()->format('YmdHis') . '.' . strtolower($arquivo->getClientOriginalExtension()),
            'public'
        );

        if (! $novoCaminho) {
            return response()->json([
                'message' => 'Não foi possível armazenar a logo.',
            ], 500);
        }

        $logoAnterior = $empresa->logo;
        $empresa->logo = $novoCaminho;
        $empresa->save();

        if (
            $logoAnterior &&
            $logoAnterior !== $novoCaminho &&
            Storage::disk('public')->exists($logoAnterior)
        ) {
            Storage::disk('public')->delete($logoAnterior);
        }

        return response()->json([
            'message' => 'Logo atualizada e vinculada aos documentos fiscais.',
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
