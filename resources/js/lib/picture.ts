/**
 * Produktbilder liegen als WebP und AVIF nebeneinander im Storage: gleicher
 * Name, nur die Endung unterscheidet sich. Das Backend liefert die
 * WebP-URL; die AVIF-URL ergibt sich daraus.
 *
 * Gibt `null` zurück, wenn die URL kein WebP ist (Altbestand, der noch nicht
 * mit `php artisan product-images:convert` umgewandelt wurde).
 */
export function avifSrc(webpSrc: string): string | null {
    const match = webpSrc.match(/^([^?#]*)\.webp([?#].*)?$/i);

    return match ? `${match[1]}.avif${match[2] ?? ''}` : null;
}
