<?php

namespace Tests\Unit;

use App\Services\Sistema\SystemLogReader;
use PHPUnit\Framework\TestCase;

class SystemLogReaderTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'intelbrastech-system-log-'.bin2hex(random_bytes(4)).'.log';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        parent::tearDown();
    }

    public function test_it_parses_multiline_entries_and_filters_level(): void
    {
        file_put_contents($this->path, implode("\n", [
            '['.now()->format('Y-m-d H:i:s').'] production.ERROR: Falha ao salvar pedido',
            '#0 /var/www/html/app/Services/PedidoService.php(42): executar()',
            '['.now()->format('Y-m-d H:i:s').'] production.INFO: Processamento concluído',
        ]));

        $result = (new SystemLogReader($this->path))->read(['level' => 'error', 'hours' => 1]);

        $this->assertCount(1, $result['entries']);
        $this->assertSame('error', $result['entries'][0]['level']);
        $this->assertSame('[app]/app/Services/PedidoService.php:42', $result['entries'][0]['source']);
    }

    public function test_it_masks_credentials_and_personal_data(): void
    {
        $reader = new SystemLogReader($this->path);
        $sanitized = $reader->sanitize('password=segredo token:abc123 elvis@intelbrastech.com.br CPF 733.696.871-34');

        $this->assertStringNotContainsString('segredo', $sanitized);
        $this->assertStringNotContainsString('abc123', $sanitized);
        $this->assertStringNotContainsString('elvis@', $sanitized);
        $this->assertStringNotContainsString('733.696.871-34', $sanitized);
        $this->assertStringContainsString('[PROTEGIDO]', $sanitized);
    }

    public function test_summary_respects_the_selected_period(): void
    {
        file_put_contents($this->path, implode("\n", [
            '['.now()->subDays(2)->format('Y-m-d H:i:s').'] production.ERROR: Erro antigo',
            '['.now()->format('Y-m-d H:i:s').'] production.WARNING: Aviso recente',
        ]));

        $result = (new SystemLogReader($this->path))->read(['hours' => 1]);

        $this->assertSame(0, $result['summary']['error']);
        $this->assertSame(1, $result['summary']['warning']);
    }
}
