<?php

namespace App\Services\Branding;

use App\Models\Empresa;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PwaBrandingService
{
    private const RENDER_VERSION = 'full-bleed-v2';

    public function version(Empresa $empresa): string
    {
        $path = $this->logoPath($empresa);

        if ($path === null) {
            return 'fallback-full-bleed-v2';
        }

        $logoHash = hash_file('sha256', $path) ?: 'fallback';

        return substr(hash('sha256', self::RENDER_VERSION . '|' . $logoHash), 0, 20);
    }

    public function renderIcon(Empresa $empresa, int $size, bool $maskable = false): string
    {
        if (! in_array($size, [32, 180, 192, 512], true)) {
            throw new RuntimeException('Tamanho de ícone PWA não suportado.');
        }

        $canvas = imagecreatetruecolor($size, $size);
        if ($canvas === false) {
            throw new RuntimeException('Não foi possível criar o ícone PWA.');
        }

        $source = $this->loadSource($empresa);
        if ($source !== null) {
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            [$red, $green, $blue] = $this->sourceBackgroundColor($source, $sourceWidth, $sourceHeight);
            $background = imagecolorallocate($canvas, $red, $green, $blue);
            imagefill($canvas, 0, 0, $background);

            // O ícone comum ocupa toda a área. No maskable, o respiro usa a
            // própria cor da logo para evitar bordas brancas após o recorte.
            $safeArea = $maskable ? 0.78 : 1.0;
            $scale = min(($size * $safeArea) / $sourceWidth, ($size * $safeArea) / $sourceHeight);
            $targetWidth = max(1, (int) round($sourceWidth * $scale));
            $targetHeight = max(1, (int) round($sourceHeight * $scale));
            $targetX = (int) floor(($size - $targetWidth) / 2);
            $targetY = (int) floor(($size - $targetHeight) / 2);

            imagealphablending($canvas, true);
            imagecopyresampled(
                $canvas,
                $source,
                $targetX,
                $targetY,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $sourceWidth,
                $sourceHeight,
            );
            imagedestroy($source);
        } else {
            $this->drawFallback($canvas, $size);
        }

        ob_start();
        imagepng($canvas, null, 6);
        $contents = ob_get_clean();
        imagedestroy($canvas);

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('Não foi possível codificar o ícone PWA.');
        }

        return $contents;
    }

    private function logoPath(Empresa $empresa): ?string
    {
        $logo = trim((string) $empresa->logo);
        if ($logo === '' || str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://')) {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($logo)) {
            return null;
        }

        return $disk->path($logo);
    }

    private function loadSource(Empresa $empresa): mixed
    {
        $path = $this->logoPath($empresa);
        if ($path === null) {
            return null;
        }

        $contents = @file_get_contents($path);
        if (! is_string($contents) || $contents === '') {
            return null;
        }

        $image = @imagecreatefromstring($contents);

        return $image === false ? null : $image;
    }

    private function sourceBackgroundColor(mixed $source, int $width, int $height): array
    {
        $sampleX = min(1, max(0, $width - 1));
        $sampleY = min(1, max(0, $height - 1));
        $color = imagecolorsforindex($source, imagecolorat($source, $sampleX, $sampleY));

        // Logos transparentes recebem o verde institucional em vez do branco.
        if (($color['alpha'] ?? 0) >= 100) {
            return [0, 163, 53];
        }

        return [(int) $color['red'], (int) $color['green'], (int) $color['blue']];
    }

    private function drawFallback(mixed $canvas, int $size): void
    {
        $green = imagecolorallocate($canvas, 0, 163, 53);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $green);

        $stroke = max(2, (int) round($size * 0.08));
        $top = (int) round($size * 0.28);
        $bottom = (int) round($size * 0.72);
        $leftI = (int) round($size * 0.31);
        $leftT = (int) round($size * 0.52);
        $rightT = (int) round($size * 0.76);

        imagefilledrectangle($canvas, $leftI, $top, $leftI + $stroke, $bottom, $white);
        imagefilledrectangle($canvas, $leftT, $top, $rightT, $top + $stroke, $white);
        imagefilledrectangle($canvas, (int) round(($leftT + $rightT - $stroke) / 2), $top, (int) round(($leftT + $rightT + $stroke) / 2), $bottom, $white);
    }
}
