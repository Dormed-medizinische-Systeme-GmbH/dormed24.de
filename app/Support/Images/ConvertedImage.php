<?php

namespace App\Support\Images;

/**
 * Ergebnis einer Umwandlung: dasselbe Bild als WebP und AVIF.
 */
final readonly class ConvertedImage
{
    public function __construct(
        public string $webp,
        public string $avif,
        public int $width,
        public int $height,
        public bool $wasResized,
    ) {}
}
