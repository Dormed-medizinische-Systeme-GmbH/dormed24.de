<script lang="ts">
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import type { SocialPostEditLayoutProps } from '@/types';

    let { fields }: SocialPostEditLayoutProps = $props();
</script>

<!-- Variante B: Text und Medien links, Veröffentlichung und Verknüpfungen rechts -->
<div
    class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:items-start"
>
    <div class="flex flex-col gap-6">
        {@render fields.title()}
        {@render fields.content()}
        {@render fields.media()}
        {@render fields.platformTexts()}
    </div>

    <div class="flex flex-col gap-6">
        <Card>
            <CardHeader>
                <CardTitle>Veröffentlichung</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-6">
                {@render fields.platforms()}
                {@render fields.scheduledAt()}
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Verknüpfungen</CardTitle>
                <CardDescription>
                    Vorbereitet, noch ohne Funktion.
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-col gap-6">
                {@render fields.link()}
                {@render fields.hashtags()}
                {@render fields.linked()}
            </CardContent>
        </Card>
    </div>
</div>
