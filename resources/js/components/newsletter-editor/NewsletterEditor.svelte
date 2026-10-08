<script lang="ts">
    import BlocksIcon from '@lucide/svelte/icons/blocks';
    import Layers from '@lucide/svelte/icons/layers';
    import Monitor from '@lucide/svelte/icons/monitor';
    import MousePointer2 from '@lucide/svelte/icons/mouse-pointer-2';
    import Paintbrush from '@lucide/svelte/icons/paintbrush';
    import Redo2 from '@lucide/svelte/icons/redo-2';
    import SlidersHorizontal from '@lucide/svelte/icons/sliders-horizontal';
    import Smartphone from '@lucide/svelte/icons/smartphone';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import Undo2 from '@lucide/svelte/icons/undo-2';
    import type { Block, Editor, ProjectData } from 'grapesjs';
    import { onMount } from 'svelte';
    import { Button } from '@/components/ui/button';
    import * as Tabs from '@/components/ui/tabs';
    import * as ToggleGroup from '@/components/ui/toggle-group';
    import BlockPalette from './BlockPalette.svelte';
    import { starterTemplate } from './template';

    export type EditorContent = {
        design_json: ProjectData;
        mjml: string;
        html: string;
        errors: string[];
    };

    export type EditorApi = {
        getContent: () => EditorContent;
    };

    type PaletteItem = { id: string; label: string; model: Block };
    type Device = 'desktop' | 'mobile';

    let {
        designJson,
        uploadUrl,
        upload,
        shopUrl,
        onReady,
        onChange,
    }: {
        designJson: ProjectData | null;
        uploadUrl: string;
        upload: (files: File[]) => Promise<string[]>;
        shopUrl: string;
        onReady: (api: EditorApi) => void;
        onChange: () => void;
    } = $props();

    let container: HTMLDivElement;
    let layersContainer: HTMLDivElement;
    let traitsContainer: HTMLDivElement;
    let stylesContainer: HTMLDivElement;

    let editor = $state.raw<Editor | null>(null);
    let loading = $state(true);
    let loadError = $state<string | null>(null);

    let leftTab = $state('blocks');
    let rightTab = $state('traits');
    let device = $state<Device>('desktop');
    let paletteItems = $state.raw<PaletteItem[]>([]);
    let canUndo = $state(false);
    let canRedo = $state(false);
    let selectedName = $state<string | null>(null);
    let selectedRemovable = $state(false);

    function refreshBlocks(current: Editor) {
        paletteItems = current.Blocks.getAll().map((block) => ({
            id: block.getId(),
            label: block.getLabel(),
            model: block,
        }));
    }

    function refreshHistory(current: Editor) {
        canUndo = current.UndoManager.hasUndo();
        canRedo = current.UndoManager.hasRedo();
    }

    function refreshSelection(current: Editor) {
        const selected = current.getSelected();
        selectedName = selected?.getName() ?? null;
        selectedRemovable = !!selected && selected.get('removable') !== false;
    }

    function selectDevice(next: string) {
        // Ein erneuter Klick auf den aktiven Eintrag liefert '' – Gerät bleibt.
        if (!editor || (next !== 'desktop' && next !== 'mobile')) {
            return;
        }

        // Die Gerätebefehle von grapesjs-mjml sind zustandsbehaftet und
        // laufen nicht erneut, solange sie aktiv sind.
        editor.stopCommand(`set-device-${device}`);
        editor.runCommand(`set-device-${next}`);
        device = next;
    }

    function undo() {
        editor?.UndoManager.undo();
    }

    function redo() {
        editor?.UndoManager.redo();
    }

    function deleteSelected() {
        editor?.runCommand('core:component-delete');
    }

    onMount(() => {
        let instance: Editor | null = null;
        let destroyed = false;

        (async () => {
            try {
                // GrapesJS ist groß und wird nur im Editor gebraucht.
                const [
                    { default: grapesjs },
                    { default: grapesjsMjml },
                    { default: grapesDe },
                    { default: mjmlDe },
                ] = await Promise.all([
                    import('grapesjs'),
                    import('grapesjs-mjml'),
                    import('grapesjs/locale/de'),
                    import('grapesjs-mjml/locale/de'),
                    import('grapesjs/dist/css/grapes.min.css'),
                ]);

                if (destroyed) {
                    return;
                }

                instance = grapesjs.init({
                    container,
                    height: '100%',
                    width: 'auto',
                    fromElement: false,
                    // Kein Font Awesome vom CDN, Icons kommen aus Lucide.
                    cssIcons: '',
                    // Keine Nutzungsdaten an app.grapesjs.com.
                    telemetry: false,
                    storageManager: false,
                    // Leisten, Tabs und Werkzeuge baut diese Komponente selbst,
                    // GrapesJS liefert nur Canvas, Datenmodell und Undo.
                    panels: { defaults: [] },
                    blockManager: { custom: true },
                    layerManager: { appendTo: layersContainer },
                    traitManager: { appendTo: traitsContainer },
                    styleManager: { appendTo: stylesContainer },
                    projectData: designJson ?? {
                        pages: [{ component: starterTemplate(shopUrl) }],
                    },
                    i18n: {
                        locale: 'de',
                        detectLocale: false,
                        messages: { de: { ...grapesDe, ...mjmlDe } },
                    },
                    plugins: [grapesjsMjml],
                    pluginsOpts: {
                        [grapesjsMjml as unknown as string]: {
                            imagePlaceholderSrc: `${shopUrl}/favicon.ico`,
                            // Farben kommen aus den Variablen unten, nicht vom Plugin.
                            useCustomTheme: false,
                            // Kein Social-Block: die Icons kämen von fremden Servern.
                            blocks: [
                                'mj-1-column',
                                'mj-2-columns',
                                'mj-3-columns',
                                'mj-text',
                                'mj-button',
                                'mj-image',
                                'mj-divider',
                                'mj-spacer',
                                'mj-hero',
                                'mj-wrapper',
                            ],
                        },
                    },
                    assetManager: {
                        upload: uploadUrl,
                        multiUpload: true,
                        embedAsBase64: false,
                        uploadFile: async (event: Event) => {
                            const target = event as DragEvent & {
                                target: HTMLInputElement;
                            };
                            const fileList =
                                target.dataTransfer?.files ??
                                target.target?.files;

                            if (!fileList || !instance) {
                                return;
                            }

                            const urls = await upload(Array.from(fileList));
                            instance.AssetManager.add(urls);
                        },
                    },
                });

                const current = instance;
                editor = current;

                refreshBlocks(current);
                refreshHistory(current);
                current.on('block:custom', () => refreshBlocks(current));
                current.on('component:toggled', () =>
                    refreshSelection(current),
                );
                current.on('update', () => {
                    refreshHistory(current);
                    refreshSelection(current);
                    onChange();
                });
                current.on('undo redo', () => refreshHistory(current));

                onReady({
                    getContent: () => {
                        const mjml = current.runCommand('mjml-code') as string;
                        const result = current.runCommand(
                            'mjml-code-to-html',
                        ) as {
                            html: string;
                            errors?: { formattedMessage?: string }[];
                        };

                        return {
                            design_json: current.getProjectData(),
                            mjml,
                            html: result.html,
                            errors: (result.errors ?? []).map(
                                (error) =>
                                    error.formattedMessage ?? String(error),
                            ),
                        };
                    },
                });
            } catch (error) {
                loadError =
                    error instanceof Error ? error.message : String(error);
            } finally {
                loading = false;
            }
        })();

        return () => {
            destroyed = true;
            instance?.destroy();
            editor = null;
        };
    });
</script>

<div
    class="newsletter-editor relative grid h-full min-h-[600px] grid-cols-[16rem_minmax(0,1fr)_18rem] overflow-hidden rounded-xl border bg-background"
>
    {#if loading}
        <div class="absolute inset-0 z-20 bg-background">
            <div class="h-full w-full animate-pulse bg-muted/50"></div>
        </div>
    {/if}

    <aside class="flex min-h-0 flex-col border-r" aria-label="Bausteine">
        <Tabs.Root bind:value={leftTab} class="p-3 pb-0">
            <Tabs.List class="w-full">
                <Tabs.Trigger value="blocks">
                    <BlocksIcon />
                    Blöcke
                </Tabs.Trigger>
                <Tabs.Trigger value="layers">
                    <Layers />
                    Ebenen
                </Tabs.Trigger>
            </Tabs.List>
        </Tabs.Root>
        <div class="min-h-0 flex-1 overflow-y-auto p-3">
            <div class:hidden={leftTab !== 'blocks'}>
                <BlockPalette
                    blocks={paletteItems}
                    onDragStart={(item, event) =>
                        editor?.Blocks.startDrag(item.model, event)}
                    onDragEnd={() => editor?.Blocks.endDrag()}
                />
            </div>
            <div
                bind:this={layersContainer}
                class:hidden={leftTab !== 'layers'}
            ></div>
        </div>
    </aside>

    <section class="flex min-h-0 min-w-0 flex-col" aria-label="Vorschau">
        <div class="flex items-center justify-between gap-2 border-b px-3 py-2">
            <ToggleGroup.Root
                type="single"
                variant="outline"
                size="sm"
                value={device}
                onValueChange={selectDevice}
                aria-label="Vorschaugröße"
            >
                <ToggleGroup.Item value="desktop" aria-label="Desktop">
                    <Monitor />
                    Desktop
                </ToggleGroup.Item>
                <ToggleGroup.Item value="mobile" aria-label="Mobil">
                    <Smartphone />
                    Mobil
                </ToggleGroup.Item>
            </ToggleGroup.Root>
            <div class="flex items-center gap-1">
                <Button
                    variant="ghost"
                    size="icon-sm"
                    onclick={undo}
                    disabled={!canUndo}
                    aria-label="Rückgängig"
                    title="Rückgängig"
                >
                    <Undo2 />
                </Button>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    onclick={redo}
                    disabled={!canRedo}
                    aria-label="Wiederholen"
                    title="Wiederholen"
                >
                    <Redo2 />
                </Button>
            </div>
        </div>
        {#if loadError}
            <p class="p-4 text-sm text-destructive">
                Der Editor konnte nicht geladen werden: {loadError}
            </p>
        {/if}
        <div bind:this={container} class="min-h-0 flex-1"></div>
    </section>

    <aside class="flex min-h-0 flex-col border-l" aria-label="Einstellungen">
        <div class="flex items-center justify-between gap-2 border-b p-3">
            <div class="min-w-0">
                <p class="text-xs text-muted-foreground">Ausgewählt</p>
                <p class="truncate text-sm font-semibold">
                    {selectedName ?? 'Nichts'}
                </p>
            </div>
            <Button
                variant="ghost"
                size="icon-sm"
                onclick={deleteSelected}
                disabled={!selectedRemovable}
                aria-label="Element löschen"
                title="Element löschen"
            >
                <Trash2 />
            </Button>
        </div>
        {#if selectedName === null}
            <div
                class="flex flex-1 flex-col items-center justify-center gap-2 p-6 text-center text-sm text-muted-foreground"
            >
                <MousePointer2 class="size-5" />
                Ein Element in der Vorschau anklicken, um es zu bearbeiten.
            </div>
        {/if}
        <div
            class={selectedName === null
                ? 'hidden'
                : 'flex min-h-0 flex-1 flex-col'}
        >
            <Tabs.Root bind:value={rightTab} class="p-3 pb-0">
                <Tabs.List class="w-full">
                    <Tabs.Trigger value="traits">
                        <SlidersHorizontal />
                        Eigenschaften
                    </Tabs.Trigger>
                    <Tabs.Trigger value="styles">
                        <Paintbrush />
                        Stil
                    </Tabs.Trigger>
                </Tabs.List>
            </Tabs.Root>
            <div class="min-h-0 flex-1 overflow-y-auto py-2">
                <div
                    bind:this={traitsContainer}
                    class:hidden={rightTab !== 'traits'}
                ></div>
                <div
                    bind:this={stylesContainer}
                    class:hidden={rightTab !== 'styles'}
                ></div>
            </div>
        </div>
    </aside>
</div>

<style>
    /*
     * GrapesJS-Farben auf das Admin-Theme umstellen. Die Variablen erben
     * in Canvas, Ebenen, Eigenschaften und Stil, weil alle in diesem
     * Element hängen.
     */
    .newsletter-editor {
        --gjs-main-font: inherit;
        --gjs-font-size: 0.8125rem;
        --gjs-primary-color: transparent;
        --gjs-secondary-color: var(--foreground);
        --gjs-tertiary-color: #1a6bbf;
        --gjs-quaternary-color: #1a6bbf;
        --gjs-font-color: var(--foreground);
        --gjs-font-color-active: var(--foreground);
        --gjs-main-dark-color: color-mix(
            in oklab,
            var(--muted) 70%,
            transparent
        );
        --gjs-secondary-dark-color: var(--muted);
        --gjs-main-light-color: var(--muted);
        --gjs-secondary-light-color: var(--muted-foreground);
        --gjs-soft-light-color: transparent;
        --gjs-light-border: var(--border);
        --gjs-arrow-color: var(--muted-foreground);
        --gjs-dark-text-shadow: transparent;
        --gjs-color-blue: #1a6bbf;
        --gjs-color-highlight: #1a6bbf;
        --gjs-left-width: 0%;
        --gjs-canvas-top: 0px;
    }

    /* Panels kommen nur noch vom Plugin (Import, Code), sie werden nicht gebraucht. */
    .newsletter-editor :global(.gjs-pn-panel) {
        display: none;
    }

    .newsletter-editor :global(.gjs-cv-canvas) {
        background-color: var(--muted);
    }

    .newsletter-editor :global(.gjs-sm-sector-title),
    .newsletter-editor :global(.gjs-layer-item) {
        border-color: var(--border);
        background-color: transparent;
        letter-spacing: normal;
    }
</style>
