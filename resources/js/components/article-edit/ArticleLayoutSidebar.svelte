<script lang="ts">
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import type { ArticleEditLayoutProps } from '@/types';

    let { fields }: ArticleEditLayoutProps = $props();
</script>

<!-- Variante A: Inhalt links, Einstellungen in der Seitenleiste (bisheriges Layout) -->
<div
    class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:items-start"
>
    <div class="flex flex-col gap-6">
        {@render fields.title()}
        {@render fields.slug()}
        {@render fields.teaser()}
        {@render fields.content()}
    </div>

    <div class="flex flex-col gap-6">
        <Card>
            <CardContent class="flex flex-col gap-6">
                {@render fields.category()}
                {@render fields.publishedAt()}
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Später verfügbar</CardTitle>
                <CardDescription>
                    Diese Felder sind vorbereitet, aber noch ohne Funktion.
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-col gap-6">
                {@render fields.cover()}
                {@render fields.seo()}
                {@render fields.meta()}
            </CardContent>
        </Card>
    </div>
</div>
