<?php

namespace Database\Seeders;

use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Images\ImageConversionException;
use App\Support\Images\ImageStore;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ProductSeeder extends Seeder
{
    use WithoutModelEvents;

    private const CSV_PATH = '.assets/Shopware-Exports/aktuellen produkte.csv';

    public function run(): void
    {
        // Schutz vor Datenverlust: dieser Seeder LÖSCHT den kompletten Katalog
        // (Produkte, Varianten, Hersteller) samt hochgeladener
        // Bilder und ersetzt ihn durch den Shopware-CSV-Stand. In Produktion
        // würde ein versehentliches `db:seed` bzw. `db:seed --class=ProductSeeder`
        // damit alle im Admin gepflegten Produkte + Bilder vernichten. Deshalb
        // läuft er in Produktion nur, wenn der Erstimport bewusst mit
        // SEED_PRODUCTS_FORCE=true angefordert wird (fail-safe: im Zweifel
        // überspringen, nichts löschen).
        if (app()->isProduction() && ! config('shop.seed_products_force')) {
            $this->command?->warn(
                'ProductSeeder in Produktion übersprungen: würde den kompletten Katalog inkl. Bilder löschen. '
                .'Für einen bewussten Erstimport SEED_PRODUCTS_FORCE=true setzen.'
            );

            return;
        }

        $rows = $this->readCsvRows(base_path(self::CSV_PATH));

        if ($rows === []) {
            $this->command?->warn('Keine Produktdaten in der CSV gefunden.');

            return;
        }

        // Seeder soll bei wiederholten Runs denselben Showcase-Stand herstellen.
        Storage::disk('public')->deleteDirectory('products');
        ProductVariant::query()->delete();
        Product::query()->delete();
        Manufacturer::query()->delete();
        foreach ($rows as $row) {
            $manufacturer = $this->resolveManufacturer($row['manufacturer_name'] ?? null);

            $name = trim((string) ($row['name'] ?? ''));

            $product = Product::create([
                'manufacturer_id' => $manufacturer?->id,
                // Slugs aus config/categories.php
                'category_slug' => 'zubehoer',
                'name' => $name,
                'slug' => Product::generateSlug($name),
                'description' => $this->nullableString($row['description'] ?? null),
                'price' => $this->decimal($row['price_gross'] ?? null),
                'is_available' => (bool) ((int) ($row['active'] ?? 0)),
            ]);

            $this->seedCoverImage($product, $row['cover_media_url'] ?? null);
        }

        $manualProductCount = $this->seedManualProducts();

        $this->command?->info(sprintf('%d Produkte aus Shopware-CSV importiert und ergaenzt.', count($rows) + $manualProductCount));
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function readCsvRows(string $path): array
    {
        if (! is_file($path)) {
            throw new \RuntimeException("CSV-Datei nicht gefunden: {$path}");
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new \RuntimeException("CSV-Datei konnte nicht geoeffnet werden: {$path}");
        }

        try {
            $header = fgetcsv($handle, 0, ';');

            if ($header === false) {
                return [];
            }

            $header = array_map(function ($value) {
                $value = (string) $value;

                return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
            }, $header);

            $rows = [];

            while (($data = fgetcsv($handle, 0, ';')) !== false) {
                if ($data === [null]) {
                    continue;
                }

                $row = [];

                foreach ($header as $index => $column) {
                    $row[$column] = $data[$index] ?? null;
                }

                if (blank($row['name'] ?? null)) {
                    continue;
                }

                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function resolveManufacturer(?string $name): ?Manufacturer
    {
        $name = $this->nullableString($name);

        if ($name === null) {
            return null;
        }

        return Manufacturer::firstOrCreate([
            'name' => $name,
        ]);
    }

    private function seedManualProducts(): int
    {
        $manufacturer = Manufacturer::firstOrCreate([
            'name' => 'Sony',
        ]);

        $categorySlug = 'verbrauchsartikel';

        $this->createManualProduct(
            manufacturer: $manufacturer,
            categorySlug: $categorySlug,
            name: 'Druckpapier UPP-110HG',
            description: 'Das UPP-110HG von Sony ist ein medizinisches Druckpapier fuer den Einsatz in der medizinischen Bildgebung. Es wird hauptsaechlich in der Ultraschalluntersuchung verwendet und ermoeglicht die Erstellung von Ausdrucken im Format A6 Typ V. Dieses Verbrauchsmaterial erfuellt die Anforderungen von medizinischen Fachkraeften, die Drucksysteme zur Wiedergabe diagnostischer Bilder einsetzen.',
            imageUrls: ['https://d17eythm3w95tp.cloudfront.net/media/201275/conversions/upp-110hg-medium.png']
        );

        $this->createManualProduct(
            manufacturer: $manufacturer,
            categorySlug: $categorySlug,
            name: 'Druckerpapier UPP-110S',
            description: "A6-Standarddruckerpapier (Typ I) fuer den Schwarzweissdruck mit den Druckerserien UP-899 / 898 / 897.\n\nDieses Produkt ist verfuegbar in Einheiten von zehn Rollen pro Karton.\n\nHauptsaechlich verwendet bei Ultraschallanwendungen sowie in der Zahnmedizin und der Mikroskopie.\n\nRollenmasse\n110 mm (B) x 20 m\n\nBestelleinheit\n10 Rollen\n\nDruckmenge\n217 Druckseiten (mit UP-895CE)",
            imageUrls: [
                'https://www.dimedtec.de/medizintechnik/media/image/product/113211/md/upp-110s-a6-high-density-druckerpapier-fuer-schwarzweissdrucker-up-895ce-d895-890ce-d890-860ce-d860.jpg',
                'https://www.doccheckshop.de/media/c6/5f/f8/1746722686/140168-dh-videopringerpapier-sony-upp-110s-neu.webp',
            ]
        );

        $mindrayManufacturer = Manufacturer::firstOrCreate([
            'name' => 'Mindray',
        ]);

        $portableUltrasound = Product::create([
            'manufacturer_id' => $mindrayManufacturer->id,
            'category_slug' => 'ultraschallsysteme',
            'name' => 'Tragbares Ultraschallsystem MU7',
            'slug' => Product::generateSlug('Tragbares Ultraschallsystem MU7'),
            'description' => "Mindray MU7 - leichtes Sonographiesystem fuer mobile Einsaetze mit starker Bildgebung und ausdauerndem Akkubetrieb\n\nDas mindray MU7 ist ein portables Ultraschallgeraet fuer Praxen und mobile Einsatzbereiche, in denen Flexibilitaet, Zuverlaessigkeit und einfache Bedienung entscheidend sind. Mit einem Gewicht von nur rund 2 kg, einer Akkulaufzeit von mehr als 4 Stunden und einer robusten, stossfesten Bauweise eignet sich dieses Sonographiegeraet besonders fuer wechselnde Untersuchungsorte und den taeglichen Einsatz im Praxisalltag. Die nano ZST+ Plattform unterstuetzt eine leistungsfaehige Bildverarbeitung, waehrend Funktionen wie iTouch, iWorks und Smart Track den Workflow spuergbar erleichtern. Das 13,3-Zoll-Touchdisplay, das versiegelte Bedienfeld und die integrierte WLAN-Funktion machen das MU7 zu einem durchdachten Ultraschallgeraet fuer Anwender, die mobil arbeiten und dabei nicht auf Komfort verzichten moechten.\n\nWas zeichnet das MU7 Ultraschallgeraet besonders aus?\n\nBesonders mobil im Praxisalltag: Mit nur rund 2 kg laesst sich das Ultraschallgeraet bequem transportieren und flexibel an verschiedenen Einsatzorten nutzen.\n\nMehr Unabhaengigkeit im Arbeitsablauf: Der Li-Ionen-Akku ermoeglicht einen netzunabhaengigen Betrieb von bis zu 4 Stunden. Das ist ideal fuer Hausbesuche, flexible Raumwechsel oder kurze Untersuchungen ohne festen Geraeteplatz.\n\nEinfach und effizient zu bedienen: Das MU7 kombiniert Touchdisplay, physische Tasten und Tastatur. Individuell anpassbare Kurzbefehle helfen dabei, Untersuchungen schneller und strukturierter durchzufuehren.\n\nZuverlaessige Bildqualitaet im Alltag: Die nano ZST+ Plattform sowie Funktionen wie iTouch, PSH, iBeam und iClear unterstuetzen eine klare Bilddarstellung und eine schnelle Bildoptimierung.\n\nPraxisgerecht und hygienisch: Das versiegelte Bedienfeld erleichtert Reinigung und Desinfektion und unterstuetzt damit einen sicheren Einsatz im medizinischen Alltag.\n\nGut in bestehende Ablaeufe integrierbar: Mit DICOM, WLAN, Netzwerkanschluss, HDMI und 3 USB 3.0 Ports laesst sich das Sonographiegeraet gut in bestehende Praxisstrukturen einbinden.\n\nKV-anmeldefaehig\n\nDas mindray MU7 Ultraschallgeraet erfuellt die wichtigen Voraussetzungen fuer die Anmeldung im KV-Umfeld und ist damit auch fuer niedergelassene Praxen eine interessante mobile Loesung. Die konkrete Genehmigung erfolgt durch die jeweils zustaendige KV.",
            'price' => $this->decimal(4390),
            'is_available' => true,
        ]);

        $portableUltrasound->variants()->create([
            'label' => 'ohne Gerätewagen',
            'price' => $this->decimal(4390),
            'sort_order' => 0,
            'is_default' => true,
        ]);

        $portableUltrasound->variants()->create([
            'label' => 'mit Gerätewagen',
            'price' => $this->decimal(5390),
            'sort_order' => 1,
            'is_default' => false,
        ]);

        $this->seedProductImages($portableUltrasound, [
            'https://static.wixstatic.com/media/30d618_1b23e23af3a34977867a130bbcc6d5c4~mv2.webp/v1/fill/w_638,h_551,al_c,q_80,usm_0.66_1.00_0.01,enc_avif,quality_auto/30d618_1b23e23af3a34977867a130bbcc6d5c4~mv2.webp',
            'https://static.wixstatic.com/media/30d618_c7a6dee259f14c6caa4f71249c0d2120~mv2.webp/v1/fill/w_638,h_551,al_c,q_80,usm_0.66_1.00_0.01,enc_avif,quality_auto/30d618_c7a6dee259f14c6caa4f71249c0d2120~mv2.webp',
        ]);

        $teAir = Product::create([
            'manufacturer_id' => $mindrayManufacturer->id,
            'category_slug' => 'ultraschallsysteme',
            'name' => 'TE Air e5M',
            'slug' => Product::generateSlug('TE Air e5M'),
            'description' => "Das Mindray TE Air e5M ist ein kabelloses Premium-Handheld-Ultraschallsystem, welches modernste Technologie mit herausragender Bildqualitaet und maximaler Flexibilitaet in einem kompakten Design vereint.\n\nDank der innovativen Free Band Technology ermoeglicht es nahtlose Frequenzwechsel zwischen 2,5 MHz und 12 MHz, sodass eine einzige Sonde fuer vielfaeltige Anwendungen genutzt werden kann - von der Abdomen- und Gefaessdiagnostik bis hin zur Schilddruesen- und Muskuloskelettalen Sonographie.\n\nDie iTouch+ Funktion passt automatisch die Presets an die jeweilige Untersuchung an und optimiert die Einstellungen in Sekundenbruchteilen. Zusaetzliche Technologien wie Acoustic Boost sorgen fuer gestochen scharfe Bilder, waehrend das wasserdichte Gehaeuse (IP68) eine einfache Desinfektion ermoeglicht.\n\nDas Mindray TE Air e5M ist unteranderem ideal fuer Allgemeinmediziner, Notfallmediziner, Orthopaedengeeignet und alle Sonographie-Liebhaber - ein perfekter Begleiter fuer die moderne, mobile Diagnostik.",
            'price' => $this->decimal(4163.81),
            'is_available' => true,
        ]);

        $teAir->variants()->create([
            'label' => 'ohne Air Capsule',
            'price' => $this->decimal(4163.81),
            'sort_order' => 0,
            'is_default' => true,
        ]);

        $teAir->variants()->create([
            'label' => 'mit Air Capsule',
            'price' => $this->decimal(4877.81),
            'sort_order' => 1,
            'is_default' => false,
        ]);

        $this->seedProductImages($teAir, [
            'https://static.wixstatic.com/media/30d618_bd77047285404791bfd2aba25ae5c13e~mv2.jpg/v1/fill/w_367,h_551,al_c,q_80,usm_0.66_1.00_0.01,enc_avif,quality_auto/30d618_bd77047285404791bfd2aba25ae5c13e~mv2.jpg',
            'https://static.wixstatic.com/media/30d618_e47e99f0ebed474c8edb72f577cc65d2~mv2.jpg/v1/fill/w_827,h_551,al_c,q_85,usm_0.66_1.00_0.01,enc_avif,quality_auto/30d618_e47e99f0ebed474c8edb72f577cc65d2~mv2.jpg',
        ]);

        return 4;
    }

    /**
     * @param  list<string>  $imageUrls
     */
    private function createManualProduct(
        Manufacturer $manufacturer,
        ?string $categorySlug,
        string $name,
        string $description,
        array $imageUrls,
    ): void {
        $product = Product::create([
            'manufacturer_id' => $manufacturer->id,
            'category_slug' => $categorySlug,
            'name' => $name,
            'slug' => Product::generateSlug($name),
            'description' => $description,
            'price' => $this->decimal(10),
            'is_available' => true,
        ]);

        $product->variants()->create([
            'label' => '1er Pack',
            'price' => $this->decimal(10),
            'sort_order' => 0,
            'is_default' => true,
        ]);

        $product->variants()->create([
            'label' => '12er Pack',
            'price' => $this->decimal(100),
            'sort_order' => 1,
            'is_default' => false,
        ]);

        $this->seedProductImages($product, $imageUrls);
    }

    private function seedCoverImage(Product $product, ?string $url): void
    {
        $url = $this->nullableString($url);

        if ($url === null) {
            return;
        }

        try {
            $response = Http::timeout(30)->retry(2, 500)->get($url);
        } catch (\Throwable $e) {
            $this->command?->warn("Bilddownload fehlgeschlagen fuer {$product->name}: {$e->getMessage()}");

            return;
        }

        if (! $response->successful()) {
            $this->command?->warn("Bilddownload fehlgeschlagen fuer {$product->name}: HTTP {$response->status()}");

            return;
        }

        $this->storeImage($product, $response->body(), 'cover', 0);
    }

    /**
     * @param  list<string>  $urls
     */
    private function seedProductImages(Product $product, array $urls): void
    {
        foreach ($urls as $index => $url) {
            $url = $this->nullableString($url);

            if ($url === null) {
                continue;
            }

            try {
                $response = Http::timeout(30)->retry(2, 500)->get($url);
            } catch (\Throwable $e) {
                $this->command?->warn("Bilddownload fehlgeschlagen fuer {$product->name}: {$e->getMessage()}");

                continue;
            }

            if (! $response->successful()) {
                $this->command?->warn("Bilddownload fehlgeschlagen fuer {$product->name}: HTTP {$response->status()}");

                continue;
            }

            $this->storeImage($product, $response->body(), 'image-'.($index + 1), $index);
        }
    }

    /**
     * Speichert das heruntergeladene Bild wie ein Upload: als WebP und AVIF.
     */
    private function storeImage(Product $product, string $contents, string $name, int $sortOrder): void
    {
        try {
            $stored = app(ImageStore::class)->store($contents, "products/{$product->id}", $name);
        } catch (ImageConversionException $e) {
            $this->command?->warn("Bild fuer {$product->name} nicht umgewandelt: {$e->getMessage()}");

            return;
        }

        $product->images()->create([
            'path' => $stored->path,
            'width' => $stored->width,
            'height' => $stored->height,
            'sort_order' => $sortOrder,
        ]);
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
