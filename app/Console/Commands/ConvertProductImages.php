<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Support\Images\ImageConversionException;
use App\Support\Images\ImageConverter;
use App\Support\Images\ImageStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Bringt bestehende Produktbilder auf den WebP/AVIF-Standard: legt zu jedem
 * Bild WebP und AVIF nebeneinander ab, trägt den neuen Pfad und die Maße in
 * `product_images` ein und löscht das Original (z. B. JPEG, PNG).
 *
 * Idempotent: Bilder mit WebP-Pfad, vorhandener AVIF-Datei und gesetzten
 * Maßen werden übersprungen. Fehlerhafte Bilder bleiben unverändert und
 * lassen den Befehl mit Fehlercode enden.
 */
#[Signature('product-images:convert {--dry-run : Nur anzeigen, was umgewandelt würde}')]
#[Description('Wandelt bestehende Produktbilder in WebP und AVIF um und löscht die Originale.')]
class ConvertProductImages extends Command
{
    public function handle(ImageStore $store): int
    {
        if (! ImageConverter::isSupported()) {
            $this->error('PHP gd ohne WebP/AVIF-Unterstützung: Umwandlung nicht möglich.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $converted = $skipped = $failed = 0;

        foreach (ProductImage::query()->orderBy('id')->cursor() as $image) {
            if ($this->isUpToDate($image)) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line("würde umwandeln: {$image->path}");
                $converted++;

                continue;
            }

            try {
                $stored = $store->convertExisting($image->path);
            } catch (ImageConversionException $e) {
                $this->warn("Bild #{$image->id} ({$image->path}): {$e->getMessage()}");
                $failed++;

                continue;
            }

            $image->update([
                'path' => $stored->path,
                'width' => $stored->width,
                'height' => $stored->height,
            ]);
            $converted++;
        }

        $verb = $dryRun ? 'umzuwandeln' : 'umgewandelt';
        $this->info("{$converted} {$verb}, {$skipped} bereits aktuell, {$failed} fehlgeschlagen.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function isUpToDate(ProductImage $image): bool
    {
        $avifPath = ImageStore::avifPath($image->path);

        return $avifPath !== null
            && $image->width !== null
            && $image->height !== null
            && Storage::disk('public')->exists($avifPath);
    }
}
