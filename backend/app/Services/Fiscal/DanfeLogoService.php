<?php

namespace App\Services\Fiscal;

use App\Models\Empresa;
use RuntimeException;
use Illuminate\Support\Facades\Storage;

class DanfeLogoService
{
    public const FISCAL_FILENAME = 'logo-fiscal.jpg';

    public function fiscalPath(Empresa $empresa): string
    {
        return 'empresas/logos/'.$empresa->id.'/'.self::FISCAL_FILENAME;
    }

    public function dataUri(?Empresa $empresa): ?string
    {
        if (! $empresa) {
            return null;
        }

        $path = $this->fiscalPath($empresa);

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        try {
            $contents = Storage::disk('public')->get($path);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }

        if ($contents === '') {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode($contents);
    }

    public function normalizePngToJpeg(string $sourcePath, int $quality = 92): string
    {
        foreach ([
            'imagecreatefrompng',
            'imagecreatetruecolor',
            'imagecolorallocate',
            'imagefill',
            'imagecopyresampled',
            'imagejpeg',
            'imagesx',
            'imagesy',
            'imagedestroy',
        ] as $function) {
            if (! function_exists($function)) {
                throw new RuntimeException(
                    "A extensão GD do PHP não disponibiliza a função {$function}()."
                );
            }
        }

        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new RuntimeException('O arquivo temporário da logo não pôde ser lido.');
        }

        $source = @imagecreatefrompng($sourcePath);

        if ($source === false) {
            throw new RuntimeException('O arquivo enviado não pôde ser interpretado como PNG.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($sourceWidth < 1 || $sourceHeight < 1) {
            imagedestroy($source);
            throw new RuntimeException('A logo possui dimensões inválidas.');
        }

        $maxWidth = 1200;
        $maxHeight = 600;
        $scale = min(1, $maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($target === false) {
            imagedestroy($source);
            throw new RuntimeException('Não foi possível preparar a imagem fiscal da logo.');
        }

        $white = imagecolorallocate($target, 255, 255, 255);
        imagefill($target, 0, 0, $white);

        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        ob_start();
        $written = imagejpeg($target, null, max(70, min(100, $quality)));
        $jpeg = ob_get_clean();

        imagedestroy($source);
        imagedestroy($target);

        if (! $written || ! is_string($jpeg) || $jpeg === '') {
            throw new RuntimeException('Não foi possível gerar a versão JPEG fiscal da logo.');
        }

        return $jpeg;
    }

    public function regenerate(Empresa $empresa): bool
    {
        $originalPath = (string) $empresa->logo;

        if ($originalPath === '' || ! Storage::disk('public')->exists($originalPath)) {
            return false;
        }

        $absolutePath = Storage::disk('public')->path($originalPath);
        $jpeg = $this->normalizePngToJpeg($absolutePath);

        return Storage::disk('public')->put($this->fiscalPath($empresa), $jpeg);
    }
}
