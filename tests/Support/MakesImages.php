<?php

namespace Tests\Support;

use App\Support\Images\ImageConverter;
use GdImage;

/**
 * Erzeugt echte Bilddateien für die Bild-Tests und überspringt sie lokal, wenn
 * PHP kein WebP/AVIF kodieren kann. In der CI (`CI=true`) schlägt der Test
 * stattdessen fehl, damit eine fehlende Erweiterung nicht unbemerkt bleibt.
 */
trait MakesImages
{
    protected function requireImageEncoding(): void
    {
        if (ImageConverter::isSupported()) {
            return;
        }

        $message = 'PHP gd ohne WebP/AVIF-Unterstützung.';

        if (getenv('CI')) {
            $this->fail($message);
        }

        $this->markTestSkipped($message);
    }

    /**
     * @param  'png'|'jpeg'|'gif'|'bmp'|'webp'|'avif'  $format
     */
    protected function imageBytes(string $format = 'png', int $width = 40, int $height = 30, bool $transparent = false): string
    {
        $image = imagecreatetruecolor($width, $height);

        if ($transparent) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        } else {
            imagefill($image, 0, 0, imagecolorallocate($image, 200, 40, 40));
        }

        return $this->encodeImage($image, $format);
    }

    private function encodeImage(GdImage $image, string $format): string
    {
        ob_start();

        match ($format) {
            'png' => imagepng($image),
            'jpeg' => imagejpeg($image),
            'gif' => imagegif($image),
            'bmp' => imagebmp($image),
            'webp' => imagewebp($image),
            'avif' => imageavif($image),
        };

        return (string) ob_get_clean();
    }
}
