<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Services\Branding\PwaBrandingService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PwaBrandingServiceTest extends TestCase
{
    public function test_it_generates_versioned_square_icons_from_company_logo(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('A extensão GD não está disponível.');
        }

        Storage::fake('public');
        $path = 'empresas/logos/1/logo.png';
        Storage::disk('public')->put($path, $this->png(240, 120, [0, 163, 53]));

        $empresa = new Empresa(['logo' => $path]);
        $service = app(PwaBrandingService::class);
        $firstVersion = $service->version($empresa);
        $icon = $service->renderIcon($empresa, 192);
        $rendered = imagecreatefromstring($icon);

        $this->assertNotFalse($rendered);
        $this->assertSame(192, imagesx($rendered));
        $this->assertSame(192, imagesy($rendered));
        $corner = imagecolorsforindex($rendered, imagecolorat($rendered, 0, 0));
        $this->assertSame([0, 163, 53], [$corner['red'], $corner['green'], $corner['blue']]);

        Storage::disk('public')->put($path, $this->png(240, 120, [0, 120, 40]));
        $this->assertNotSame($firstVersion, $service->version($empresa));

        imagedestroy($rendered);
    }

    private function png(int $width, int $height, array $rgb): string
    {
        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $color = imagecolorallocate($image, ...$rgb);
        imagefill($image, 0, 0, $white);
        imagefilledrectangle($image, 12, 12, $width - 13, $height - 13, $color);
        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return (string) $contents;
    }
}
