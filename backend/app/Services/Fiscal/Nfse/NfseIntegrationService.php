<?php

namespace App\Services\Fiscal\Nfse;

use App\Models\ApiIntegration;
use App\Models\CertificadoDigital;

class NfseIntegrationService
{
    public const PROVIDER = 'nfse_nacional';

    public function validateConfiguration(ApiIntegration $integration): array
    {
        $settings = $integration->settings ?? [];
        $environment = (string) ($settings['environment'] ?? 'restricted');

        if (! in_array($environment, ['restricted', 'production'], true)) {
            return [false, 'Selecione produção restrita ou produção.'];
        }

        $baseUrlKey = $environment === 'production' ? 'production_base_url' : 'restricted_base_url';
        if (blank($settings[$baseUrlKey] ?? null)) {
            return [false, 'A URL oficial do ambiente selecionado não foi informada.'];
        }

        if (($settings['layout_version'] ?? null) !== '1.01') {
            return [false, 'O motor está preparado para o leiaute oficial NFS-e 1.01.'];
        }

        $series = trim((string) ($settings['dps_series'] ?? ''));
        if ($series === '' || mb_strlen($series) > 5 || ! preg_match('/^[A-Za-z0-9]{1,5}$/', $series)) {
            return [false, 'A série DPS deve conter de 1 a 5 letras ou números.'];
        }

        $certificate = CertificadoDigital::query()
            ->where('empresa_id', $integration->empresa_id)
            ->whereKey($settings['certificate_id'] ?? null)
            ->where('ativo', true)
            ->first();

        if (! $certificate) {
            return [false, 'Selecione um certificado digital A1 ativo da empresa.'];
        }
        if ($certificate->validade && $certificate->validade->isPast()) {
            return [false, 'O certificado digital selecionado está vencido.'];
        }
        if (strtoupper((string) $certificate->tipo) !== 'A1') {
            return [false, 'A integração automática exige certificado digital A1.'];
        }
        if ($environment === 'production' && ! (bool) ($settings['api_credential_confirmed'] ?? false)) {
            return [false, 'Confirme o credenciamento antes de habilitar o ambiente de produção.'];
        }

        $keyMessage = filled($integration->getCredentials()['api_key'] ?? null)
            ? ' Chave complementar armazenada com criptografia.'
            : ' A autenticação será feita pelo certificado A1 (mTLS), sem chave complementar.';

        return [true, 'Configuração NFS-e válida para '.($environment === 'production' ? 'produção' : 'produção restrita').'.'.$keyMessage];
    }

    public function configuredForCompany(int $companyId): ?ApiIntegration
    {
        return ApiIntegration::query()
            ->where('empresa_id', $companyId)
            ->where('provider', self::PROVIDER)
            ->where('enabled', true)
            ->where('status', 'connected')
            ->first();
    }
}
