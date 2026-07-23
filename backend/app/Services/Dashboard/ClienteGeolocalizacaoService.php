<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ClienteGeolocalizacaoService
{
    public function sincronizar(int $empresaId, int $limite = 100): array
    {
        if (!Schema::hasTable('clientes')) {
            return ['processados' => 0, 'atualizados' => 0, 'falhas' => 0];
        }

        $cepColumn = $this->primeiraColunaExistente('clientes', ['cep', 'cep_endereco', 'endereco_cep']);

        if (!$cepColumn) {
            return ['processados' => 0, 'atualizados' => 0, 'falhas' => 0, 'aviso' => 'Coluna CEP não encontrada em clientes.'];
        }

        $query = DB::table('clientes')
            ->where('empresa_id', $empresaId)
            ->whereNotNull($cepColumn)
            ->where($cepColumn, '<>', '')
            ->where(function ($q) {
                $q->whereNull('latitude')->orWhereNull('longitude');
            })
            ->limit(max(1, min($limite, 300)));

        $clientes = $query->get(['id', $cepColumn]);

        $processados = 0;
        $atualizados = 0;
        $falhas = 0;

        foreach ($clientes as $cliente) {
            $processados++;
            $cep = preg_replace('/\D+/', '', (string) $cliente->{$cepColumn});

            if (strlen($cep) !== 8) {
                $falhas++;
                continue;
            }

            try {
                $coordenadas = $this->consultarCep($cep);

                if (!$coordenadas) {
                    $falhas++;
                    continue;
                }

                DB::table('clientes')->where('id', $cliente->id)->update([
                    'latitude' => $coordenadas['latitude'],
                    'longitude' => $coordenadas['longitude'],
                    'geolocalizacao_fonte' => $coordenadas['fonte'],
                    'geolocalizacao_em' => now(),
                    'updated_at' => now(),
                ]);

                $atualizados++;
            } catch (Throwable $e) {
                report($e);
                $falhas++;
            }
        }

        return compact('processados', 'atualizados', 'falhas');
    }

    public function consultarCep(string $cep): ?array
    {
        $cep = preg_replace('/\D+/', '', $cep);

        if (strlen($cep) !== 8) {
            return null;
        }

        // Primeira tentativa: BrasilAPI CEP v2, que pode fornecer latitude/longitude.
        try {
            $response = Http::timeout(12)
                ->retry(2, 500)
                ->acceptJson()
                ->withHeaders(['User-Agent' => 'IntelbrasTech-ERP/1.0'])
                ->get("https://brasilapi.com.br/api/cep/v2/{$cep}");

            if ($response->successful()) {
                $json = $response->json();
                $coordinates = $json['location']['coordinates'] ?? null;

                $longitude = isset($coordinates['longitude']) ? (float) $coordinates['longitude'] : null;
                $latitude = isset($coordinates['latitude']) ? (float) $coordinates['latitude'] : null;

                if ($this->validas($latitude, $longitude)) {
                    return [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'fonte' => 'brasilapi_cep_v2',
                    ];
                }

                // Se a API devolveu endereço sem coordenadas, usamos geocodificação por endereço.
                $endereco = implode(', ', array_filter([
                    $json['street'] ?? null,
                    $json['neighborhood'] ?? null,
                    $json['city'] ?? null,
                    $json['state'] ?? null,
                    'Brasil',
                ]));

                if ($endereco !== '') {
                    $osm = $this->consultarNominatim($endereco);

                    if ($osm) {
                        return $osm;
                    }
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        return null;
    }

    private function consultarNominatim(string $endereco): ?array
    {
        $response = Http::timeout(15)
            ->retry(1, 1000)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'IntelbrasTech-ERP/1.0 contato@intelbrastech.com.br',
                'Accept-Language' => 'pt-BR,pt;q=0.9',
            ])
            ->get('https://nominatim.openstreetmap.org/search', [
                'q' => $endereco,
                'format' => 'jsonv2',
                'limit' => 1,
                'countrycodes' => 'br',
            ]);

        if (!$response->successful()) {
            return null;
        }

        $item = $response->json()[0] ?? null;

        if (!$item) {
            return null;
        }

        $latitude = isset($item['lat']) ? (float) $item['lat'] : null;
        $longitude = isset($item['lon']) ? (float) $item['lon'] : null;

        if (!$this->validas($latitude, $longitude)) {
            return null;
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'fonte' => 'openstreetmap_nominatim',
        ];
    }

    private function validas(?float $latitude, ?float $longitude): bool
    {
        return $latitude !== null
            && $longitude !== null
            && $latitude >= -90 && $latitude <= 90
            && $longitude >= -180 && $longitude <= 180;
    }

    private function primeiraColunaExistente(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }
}
