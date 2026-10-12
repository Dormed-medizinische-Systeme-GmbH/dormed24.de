<?php

namespace App\Support\Images;

use RuntimeException;

class ImageConversionException extends RuntimeException
{
    public static function unsupportedRuntime(): self
    {
        return new self('Der Server kann keine WebP- und AVIF-Bilder erzeugen (PHP-Erweiterung gd mit WebP- und AVIF-Unterstützung fehlt).');
    }

    public static function unreadable(): self
    {
        return new self('Die Datei ist kein lesbares Bild.');
    }

    public static function tooLarge(int $width, int $height): self
    {
        return new self("Das Bild ist mit {$width} × {$height} Pixeln zu groß.");
    }

    public static function encodingFailed(string $format): self
    {
        return new self("Das Bild konnte nicht nach {$format} umgewandelt werden.");
    }
}
