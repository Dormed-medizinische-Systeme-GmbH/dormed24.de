<script lang="ts">
    import ProductImagesEditor from '@/components/product-edit/ProductImagesEditor.svelte';
    import ProductRatingsList from '@/components/product-edit/ProductRatingsList.svelte';
    import ProductVariantsEditor from '@/components/product-edit/ProductVariantsEditor.svelte';
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import type { ProductEditLayoutProps } from '@/types';

    let { product, ratings, fields, onsubmit }: ProductEditLayoutProps =
        $props();
</script>

<!-- Variante B: eine Seite, Karten links, Status/Zuordnung/Bilder rechts -->
<div
    class="mx-auto grid w-full max-w-6xl items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]"
>
    <div class="flex min-w-0 flex-col gap-6">
        <Card>
            <CardHeader>
                <CardTitle>Allgemein</CardTitle>
                <CardDescription>
                    Name und Beschreibung, wie sie im Shop erscheinen.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form {onsubmit} class="flex flex-col gap-6">
                    {@render fields.name()}
                    {@render fields.description()}
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Technische Daten</CardTitle>
                <CardDescription>
                    Erscheint im Shop im Reiter „Technische Daten“. Leere Zeilen
                    werden beim Speichern ignoriert.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {@render fields.specs(true)}
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Variationen</CardTitle>
                <CardDescription>
                    Optionale Preisvarianten, z.B. Packungsgrößen. Ohne
                    Variationen gilt der Produktpreis.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ProductVariantsEditor
                    productId={product.id}
                    variants={product.variants}
                    showHeader={false}
                />
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Bewertungen</CardTitle>
                <CardDescription>
                    Von Käufern, sofort öffentlich. Unpassende lassen sich
                    löschen.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ProductRatingsList {ratings} showHeader={false} />
            </CardContent>
        </Card>
    </div>

    <aside class="flex min-w-0 flex-col gap-6 lg:sticky lg:top-0">
        {@render fields.availability()}

        <Card>
            <CardHeader>
                <CardTitle>Preis &amp; Zuordnung</CardTitle>
            </CardHeader>
            <CardContent>
                <form {onsubmit} class="flex flex-col gap-6">
                    {@render fields.price()}
                    {@render fields.category()}
                    {@render fields.specialties()}
                    {@render fields.manufacturer()}
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Bilder</CardTitle>
            </CardHeader>
            <CardContent>
                <ProductImagesEditor
                    productId={product.id}
                    images={product.images}
                    columns={2}
                    showHeader={false}
                />
            </CardContent>
        </Card>
    </aside>
</div>
