<?php

namespace App\Support\Images;

/**
 * Ein gespeichertes Bild: `path` ist die WebP-Datei, die AVIF-Datei liegt
 * daneben mit gleichem Namen (siehe `ImageStore::avifPath()`).
 */
final readonly class StoredImage
{
    public function __construct(
        public string $path,
        public int $width,
        public int $height,
    ) {}
}
