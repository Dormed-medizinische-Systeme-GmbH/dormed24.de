import Columns2 from '@lucide/svelte/icons/columns-2';
import Columns3 from '@lucide/svelte/icons/columns-3';
import Image from '@lucide/svelte/icons/image';
import LayoutTemplate from '@lucide/svelte/icons/layout-template';
import Minus from '@lucide/svelte/icons/minus';
import MousePointerClick from '@lucide/svelte/icons/mouse-pointer-click';
import MoveVertical from '@lucide/svelte/icons/move-vertical';
import PanelTop from '@lucide/svelte/icons/panel-top';
import Square from '@lucide/svelte/icons/square';
import Type from '@lucide/svelte/icons/type';
import type { Component } from 'svelte';

/** Was die Blockleiste von einem GrapesJS-Block braucht. */
export type PaletteBlock = {
    id: string;
    label: string;
};

export type BlockGroup<T extends PaletteBlock = PaletteBlock> = {
    title: string;
    blocks: T[];
};

type BlockMeta = {
    group: 'Layout' | 'Inhalt';
    label: string;
    icon: Component;
};

/**
 * Gruppe, deutscher Name und Icon je Block-ID von grapesjs-mjml.
 * Unbekannte Blöcke landen unter "Weitere" mit dem Namen aus GrapesJS.
 */
export const BLOCK_META: Record<string, BlockMeta> = {
    'mj-1-column': { group: 'Layout', label: '1 Spalte', icon: Square },
    'mj-2-columns': { group: 'Layout', label: '2 Spalten', icon: Columns2 },
    'mj-3-columns': { group: 'Layout', label: '3 Spalten', icon: Columns3 },
    'mj-wrapper': { group: 'Layout', label: 'Bereich', icon: LayoutTemplate },
    'mj-hero': { group: 'Layout', label: 'Titelbild', icon: PanelTop },
    'mj-spacer': { group: 'Layout', label: 'Abstand', icon: MoveVertical },
    'mj-text': { group: 'Inhalt', label: 'Text', icon: Type },
    'mj-button': {
        group: 'Inhalt',
        label: 'Button',
        icon: MousePointerClick,
    },
    'mj-image': { group: 'Inhalt', label: 'Bild', icon: Image },
    'mj-divider': { group: 'Inhalt', label: 'Trennlinie', icon: Minus },
};

const GROUP_ORDER = ['Layout', 'Inhalt', 'Weitere'];

export function blockLabel(block: PaletteBlock): string {
    return BLOCK_META[block.id]?.label ?? block.label;
}

export function blockIcon(block: PaletteBlock): Component {
    return BLOCK_META[block.id]?.icon ?? Square;
}

/**
 * Sortiert Blöcke in feste Gruppen und filtert nach dem Suchbegriff
 * (Groß-/Kleinschreibung egal, deutscher Name oder GrapesJS-Name).
 * Leere Gruppen fallen weg.
 */
export function groupBlocks<T extends PaletteBlock>(
    blocks: T[],
    query = '',
): BlockGroup<T>[] {
    const needle = query.trim().toLocaleLowerCase('de');
    const groups = new Map<string, T[]>(
        GROUP_ORDER.map((title) => [title, []]),
    );

    for (const block of blocks) {
        const matches =
            needle === '' ||
            blockLabel(block).toLocaleLowerCase('de').includes(needle) ||
            block.label.toLocaleLowerCase('de').includes(needle);

        if (matches) {
            groups.get(BLOCK_META[block.id]?.group ?? 'Weitere')!.push(block);
        }
    }

    return GROUP_ORDER.map((title) => ({
        title,
        blocks: groups.get(title)!,
    })).filter((group) => group.blocks.length > 0);
}
