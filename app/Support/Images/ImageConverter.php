<?php

namespace App\Support\Images;

use GdImage;

/**
 * Wandelt ein beliebiges von GD lesbares Bild (JPEG, PNG, GIF, BMP, WebP,
 * AVIF) in WebP und AVIF um. Berücksichtigt die EXIF-Ausrichtung von
 * Handyfotos, erhält Transparenz und verkleinert zu große Bilder.
 */
class ImageConverter
{
    /** Längste Kante in Pixeln; größere Bilder werden verkleinert. */
    public const MAX_EDGE = 2400;

    /** Schutz vor Speicherüberlauf beim Dekodieren (GD hält 4 Byte pro Pixel; 24 MP ≈ 96 MB bei memory_limit 256M). */
    public const MAX_PIXELS = 24_000_000;

    public const WEBP_QUALITY = 82;

    public const AVIF_QUALITY = 60;

    public static function isSupported(): bool
    {
        return function_exists('imagecreatefromstring')
            && function_exists('imagewebp')
            && function_exists('imageavif');
    }

    /**
     * @throws ImageConversionException
     */
    public function convert(string $contents): ConvertedImage
    {
        if (! self::isSupported()) {
            throw ImageConversionException::unsupportedRuntime();
        }

        $size = @getimagesizefromstring($contents);

        if ($size === false) {
            throw ImageConversionException::unreadable();
        }

        [$width, $height] = $size;

        if ($width < 1 || $height < 1 || $width * $height > self::MAX_PIXELS) {
            throw ImageConversionException::tooLarge($width, $height);
        }

        $image = @imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            throw ImageConversionException::unreadable();
        }

        $oriented = $this->orient($image, $contents);
        $image = $this->scaleDown($oriented);
        $resized = $image !== $oriented;

        // Paletten- und Transparenz-Bilder (GIF, PNG-8) vor dem Kodieren in
        // Truecolor wandeln, sonst gehen Farben oder der Alphakanal verloren.
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return new ConvertedImage(
            webp: $this->encode('WebP', fn () => imagewebp($image, null, self::WEBP_QUALITY)),
            avif: $this->encode('AVIF', fn () => imageavif($image, null, self::AVIF_QUALITY)),
            width: imagesx($image),
            height: imagesy($image),
            wasResized: $resized,
        );
    }

    /**
     * Dreht das Bild laut EXIF-Orientierung (nur JPEG), weil das Kodieren die
     * Metadaten verwirft und Hochkantfotos sonst auf der Seite liegen.
     */
    private function orient(GdImage $image, string $contents): GdImage
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($contents, "\xFF\xD8")) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($contents));
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);

                return $image;
            case 3:
                return imagerotate($image, 180, 0) ?: $image;
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);

                return $image;
            case 5:
                imageflip($image, IMG_FLIP_VERTICAL);

                return imagerotate($image, -90, 0) ?: $image;
            case 6:
                return imagerotate($image, -90, 0) ?: $image;
            case 7:
                imageflip($image, IMG_FLIP_HORIZONTAL);

                return imagerotate($image, -90, 0) ?: $image;
            case 8:
                return imagerotate($image, 90, 0) ?: $image;
            default:
                return $image;
        }
    }

    private function scaleDown(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= self::MAX_EDGE) {
            return $image;
        }

        $scaled = imagescale($image, (int) round($width * self::MAX_EDGE / $longest), -1, IMG_BICUBIC);

        return $scaled instanceof GdImage ? $scaled : $image;
    }

    /**
     * @param  callable(): bool  $write  Schreibt das Bild nach stdout.
     */
    private function encode(string $format, callable $write): string
    {
        ob_start();
        $written = $write();
        $encoded = (string) ob_get_clean();

        if (! $written || $encoded === '') {
            throw ImageConversionException::encodingFailed($format);
        }

        return $encoded;
    }
}
