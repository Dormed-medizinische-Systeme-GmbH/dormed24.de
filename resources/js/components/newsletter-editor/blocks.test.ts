import { describe, expect, it } from 'vitest';
import { blockLabel, groupBlocks } from './blocks';

const blocks = [
    { id: 'mj-text', label: 'Text' },
    { id: 'mj-2-columns', label: '2 Columns' },
    { id: 'mj-button', label: 'Button' },
    { id: 'mj-custom', label: 'Social' },
    { id: 'mj-1-column', label: '1 Column' },
];

describe('groupBlocks', () => {
    it('sorts blocks into Layout, Inhalt and Weitere in fixed order', () => {
        const groups = groupBlocks(blocks);

        expect(groups.map((group) => group.title)).toEqual([
            'Layout',
            'Inhalt',
            'Weitere',
        ]);
        expect(groups[0].blocks.map((block) => block.id)).toEqual([
            'mj-2-columns',
            'mj-1-column',
        ]);
        expect(groups[1].blocks.map((block) => block.id)).toEqual([
            'mj-text',
            'mj-button',
        ]);
        expect(groups[2].blocks.map((block) => block.id)).toEqual([
            'mj-custom',
        ]);
    });

    it('filters by German label, case-insensitive', () => {
        const groups = groupBlocks(blocks, '  SPALTE ');

        expect(groups).toHaveLength(1);
        expect(groups[0].blocks.map((block) => block.id)).toEqual([
            'mj-2-columns',
            'mj-1-column',
        ]);
    });

    it('also matches the original GrapesJS label', () => {
        const groups = groupBlocks(blocks, 'social');

        expect(groups).toEqual([
            {
                title: 'Weitere',
                blocks: [{ id: 'mj-custom', label: 'Social' }],
            },
        ]);
    });

    it('returns no groups when nothing matches', () => {
        expect(groupBlocks(blocks, 'gibt es nicht')).toEqual([]);
    });

    it('keeps extra properties of the blocks', () => {
        const model = { any: 'thing' };
        const [group] = groupBlocks([{ id: 'mj-text', label: 'Text', model }]);

        expect(group.blocks[0].model).toBe(model);
    });
});

describe('blockLabel', () => {
    it('uses the German name for known blocks and the GrapesJS name otherwise', () => {
        expect(blockLabel({ id: 'mj-image', label: 'Image' })).toBe('Bild');
        expect(blockLabel({ id: 'mj-raw', label: 'Raw' })).toBe('Raw');
    });
});
