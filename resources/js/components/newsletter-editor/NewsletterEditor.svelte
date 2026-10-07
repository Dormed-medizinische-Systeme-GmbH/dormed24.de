<script lang="ts">
    import type { Editor, ProjectData } from 'grapesjs';
    import { onMount } from 'svelte';
    import { PANEL_ICONS } from './panel-icons';
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
    let loading = $state(true);
    let loadError = $state<string | null>(null);

    onMount(() => {
        let editor: Editor | null = null;
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

                editor = grapesjs.init({
                    container,
                    height: '100%',
                    width: 'auto',
                    fromElement: false,
                    // Kein Font Awesome vom CDN, Icons siehe panel-icons.ts.
                    cssIcons: '',
                    // Keine Nutzungsdaten an app.grapesjs.com.
                    telemetry: false,
                    storageManager: false,
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

                            if (!fileList || !editor) {
                                return;
                            }

                            const urls = await upload(Array.from(fileList));
                            editor.AssetManager.add(urls);
                        },
                    },
                });

                editor.Panels.getPanels().forEach((panel) => {
                    panel.buttons.forEach((button) => {
                        const icon = PANEL_ICONS[button.id];

                        if (icon) {
                            button.set({ className: '', label: icon });
                        }
                    });
                });
                // Blöcke statt Style-Manager offen, wie ein Klick auf den Button.
                editor.Panels.getButton('views', 'open-blocks')?.set(
                    'active',
                    true,
                );

                editor.on('update', () => onChange());

                onReady({
                    getContent: () => {
                        const current = editor as Editor;
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
            editor?.destroy();
        };
    });
</script>

<div class="relative h-full min-h-[600px] overflow-hidden rounded-xl border">
    {#if loading}
        <div
            class="absolute inset-0 z-10 flex items-center justify-center bg-background"
        >
            <div class="h-full w-full animate-pulse bg-muted/50"></div>
        </div>
    {/if}
    {#if loadError}
        <p class="p-4 text-sm text-destructive">
            Der Editor konnte nicht geladen werden: {loadError}
        </p>
    {/if}
    <div bind:this={container} class="h-full"></div>
</div>
