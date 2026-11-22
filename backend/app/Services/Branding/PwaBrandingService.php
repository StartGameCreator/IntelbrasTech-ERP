<?php

namespace App\Services\Branding;

use App\Models\Empresa;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PwaBrandingService
{
    private const RENDER_VERSION = 'full-bleed-v3-trim';

    public function version(Empresa $empresa): string
    {
        $path = $this->logoPath($empresa);

        if ($path === null) {
            return 'fallback-full-bleed-v3-trim';
        }

        $logoHash = hash_file('sha256', $path) ?: 'fallback';

        return substr(hash('sha256', self::RENDER_VERSION . '|' . $logoHash), 0, 20);
    }

    public function renderIcon(Empresa $empresa, int $size, bool $maskable = false): string
    {
        if (! in_array($size, [32, 180, 192, 512], true)) {
            throw new RuntimeException('Tamanho de ícone PWA não suportado.');
        }

        $version = $this->version($empresa);
        $variant = $maskable ? 'maskable' : 'any';

        return Cache::rememberForever(
            "pwa-icon:{$version}:{$size}:{$variant}",
            fn (): string => $this->renderIconWithoutCache($empresa, $size, $maskable),
        );
    }

    private function renderIconWithoutCache(Empresa $empresa, int $size, bool $maskable): string
    {
        $canvas = imagecreatetruecolor($size, $size);
        if ($canvas === false) {
            throw new RuntimeException('Não foi possível criar o ícone PWA.');
        }

        $source = $this->loadSource($empresa);
        if ($source !== null) {
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            [$sourceX, $sourceY, $contentWidth, $contentHeight] = $this->contentBounds($source, $sourceWidth, $sourceHeight);
            [$red, $green, $blue] = $this->sourceBackgroundColor($source, $sourceX, $sourceY);
            $background = imagecolorallocate($canvas, $red, $green, $blue);
            imagefill($canvas, 0, 0, $background);

            // O ícone comum ocupa toda a área. No maskable, o respiro usa a
            // própria cor da logo para evitar bordas brancas após o recorte.
            $safeArea = $maskable ? 0.78 : 1.0;
            $scale = min(($size * $safeArea) / $contentWidth, ($size * $safeArea) / $contentHeight);
            $targetWidth = max(1, (int) round($contentWidth * $scale));
            $targetHeight = max(1, (int) round($contentHeight * $scale));
            $targetX = (int) floor(($size - $targetWidth) / 2);
            $targetY = (int) floor(($size - $targetHeight) / 2);

            imagealphablending($canvas, true);
            imagecopyresampled(
                $canvas,
                $source,
                $targetX,
                $targetY,
                $sourceX,
                $sourceY,
                $targetWidth,
                $targetHeight,
                $contentWidth,
                $contentHeight,
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

    private function contentBounds(mixed $source, int $width, int $height): array
    {
        $corner = imagecolorsforindex($source, imagecolorat($source, 0, 0));
        $trimTransparent = ($corner['alpha'] ?? 0) >= 100;
        $trimWhite = $corner['red'] >= 245 && $corner['green'] >= 245 && $corner['blue'] >= 245;

        if (! $trimTransparent && ! $trimWhite) {
            return [0, 0, $width, $height];
        }

        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel = imagecolorsforindex($source, imagecolorat($source, $x, $y));
                $transparent = ($pixel['alpha'] ?? 0) >= 100;
                $nearWhite = $pixel['red'] >= 245 && $pixel['green'] >= 245 && $pixel['blue'] >= 245;
                if (($trimTransparent && $transparent) || ($trimWhite && $nearWhite)) {
                    continue;
                }

                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }

        if ($maxX < $minX || $maxY < $minY) {
            return [0, 0, $width, $height];
        }

        return [$minX, $minY, $maxX - $minX + 1, $maxY - $minY + 1];
    }

    private function sourceBackgroundColor(mixed $source, int $sampleX, int $sampleY): array
    {
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
