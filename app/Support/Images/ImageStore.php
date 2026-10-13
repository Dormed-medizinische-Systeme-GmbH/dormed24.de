<?php

namespace App\Support\Images;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Speichert Bilder ausschließlich als WebP und AVIF nebeneinander auf dem
 * public-Disk: gleicher Name, nur die Endung unterscheidet sich. Welches
 * Format hochgeladen wurde, spielt keine Rolle; das Original wird nicht
 * aufbewahrt.
 */
class ImageStore
{
    private const DISK = 'public';

    public function __construct(private readonly ImageConverter $converter) {}

    /**
     * Wandelt `$contents` um und legt `{$directory}/{$name}.webp` und
     * `{$directory}/{$name}.avif` ab. Ohne `$name` entsteht ein Zufallsname.
     *
     * @throws ImageConversionException
     */
    public function store(string $contents, string $directory, ?string $name = null): StoredImage
    {
        $converted = $this->converter->convert($contents);
        $webpPath = trim($directory, '/').'/'.($name ?? Str::random(40)).'.webp';

        $this->write($webpPath, $converted);

        return new StoredImage($webpPath, $converted->width, $converted->height);
    }

    /**
     * Erzeugt zu einer vorhandenen Bilddatei (z. B. JPEG, PNG) die
     * WebP/AVIF-Paare und entfernt das Original, sofern es nicht selbst die
     * WebP-Datei ist. Eine bereits ausreichend kleine WebP-Datei bleibt
     * unangetastet, damit sie nicht ein zweites Mal verlustbehaftet kodiert
     * wird; nur das AVIF kommt dazu.
     *
     * @throws ImageConversionException
     */
    public function convertExisting(string $path): StoredImage
    {
        $disk = Storage::disk(self::DISK);
        $contents = $disk->get($path);

        if ($contents === null) {
            throw ImageConversionException::unreadable();
        }

        $converted = $this->converter->convert($contents);
        $webpPath = preg_replace('/\.[^.\/]+$/', '', $path).'.webp';
        $isWebp = $path === $webpPath;

        if ($isWebp && ! $converted->wasResized) {
            $disk->put(self::avifPath($webpPath), $converted->avif);
        } else {
            $this->write($webpPath, $converted);
        }

        if (! $isWebp) {
            $disk->delete($path);
        }

        return new StoredImage($webpPath, $converted->width, $converted->height);
    }

    /**
     * Löscht die WebP-Datei und das AVIF daneben.
     */
    public function delete(string $path): void
    {
        $paths = [$path];

        if (self::isWebp($path)) {
            $paths[] = self::avifPath($path);
        }

        Storage::disk(self::DISK)->delete($paths);
    }

    public static function isWebp(string $path): bool
    {
        return preg_match('/\.webp$/i', $path) === 1;
    }

    /**
     * Pfad der AVIF-Datei neben der WebP-Datei; `null`, wenn `$path` kein
     * WebP ist (Altbestand, der noch nicht umgewandelt wurde).
     */
    public static function avifPath(string $path): ?string
    {
        return self::isWebp($path) ? preg_replace('/\.webp$/i', '.avif', $path) : null;
    }

    private function write(string $webpPath, ConvertedImage $converted): void
    {
        $disk = Storage::disk(self::DISK);
        $avifPath = (string) self::avifPath($webpPath);

        $disk->put($webpPath, $converted->webp);

        if (! $disk->put($avifPath, $converted->avif)) {
            $disk->delete($webpPath);

            throw ImageConversionException::encodingFailed('AVIF');
        }
    }
}
