<script lang="ts">
    import { Badge } from '@/components/ui/badge';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { NativeSelect } from '@/components/ui/native-select';
    import { Textarea } from '@/components/ui/textarea';

    /**
     * Platzhalter für ein Feld, das es später geben soll, aber noch nicht gibt
     * (Backend fehlt). Bewusst ohne `name` und ohne Bindung: Es wird nie
     * mitgesendet und ändert keine Formulardaten.
     */
    let {
        label,
        hint,
        kind = 'input',
        placeholder = '',
    }: {
        label: string;
        hint?: string;
        kind?: 'input' | 'textarea' | 'select' | 'file';
        placeholder?: string;
    } = $props();

    const id = $props.id();
</script>

<div class="flex flex-col gap-2" data-testid="disabled-field">
    <div class="flex items-center gap-2">
        <Label for={id} class="text-muted-foreground">{label}</Label>
        <Badge variant="outline" class="text-[10px] font-normal">
            Bald verfügbar
        </Badge>
    </div>
    {#if kind === 'textarea'}
        <Textarea {id} disabled rows={3} {placeholder} />
    {:else if kind === 'select'}
        <NativeSelect {id} disabled class="w-full">
            <option>{placeholder}</option>
        </NativeSelect>
    {:else if kind === 'file'}
        <Input {id} type="file" disabled />
    {:else}
        <Input {id} disabled {placeholder} />
    {/if}
    {#if hint}
        <p class="text-sm text-muted-foreground">{hint}</p>
    {/if}
</div>
