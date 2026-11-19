<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Services\Fiscal\DanfeLogoService;
use Illuminate\Console\Command;

class RegenerarLogosFiscais extends Command
{
    protected $signature = 'fiscal:regenerar-logos {--empresa=}';

    protected $description = 'Gera versões JPEG compatíveis com o DANFE para as logos cadastradas.';

    public function handle(DanfeLogoService $service): int
    {
        $query = Empresa::query()->whereNotNull('logo')->where('logo', '<>', '');

        if ($empresaId = $this->option('empresa')) {
            $query->whereKey($empresaId);
        }

        $total = 0;
        $ok = 0;

        $query->orderBy('id')->each(function (Empresa $empresa) use ($service, &$total, &$ok): void {
            $total++;

            try {
                if ($service->regenerate($empresa)) {
                    $ok++;
                    $this->info("Empresa {$empresa->id}: logo fiscal gerada.");
                } else {
                    $this->warn("Empresa {$empresa->id}: logo original não encontrada.");
                }
            } catch (\Throwable $e) {
                report($e);
                $this->error("Empresa {$empresa->id}: {$e->getMessage()}");
            }
        });

        $this->newLine();
        $this->line("Resultado: {$ok} de {$total} logo(s) gerada(s).");

        return $ok === $total ? self::SUCCESS : self::FAILURE;
    }
}
