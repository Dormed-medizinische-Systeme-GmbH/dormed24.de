import { describe, expect, it } from 'vitest';
import { naturalImageSizes, setOutlookImageHeights } from './outlook-images';

const src = 'https://shop.example/storage/newsletters/geraet.png';
const sizes = new Map([[src, { width: 1000, height: 1300 }]]);

function img(attributes: string): string {
    return `<img\n         alt="" ${attributes} style="border:0;display:block;height:auto;width:100%;" />`;
}

describe('setOutlookImageHeights', () => {
    it('replaces height="auto" with the height for the rendered width', () => {
        const html = `<td style="width:430px;">${img(`src="${src}" width="430" height="auto"`)}</td>`;

        const result = setOutlookImageHeights(html, sizes);

        expect(result).toContain('width="430" height="559"');
        expect(result).not.toContain('height="auto"');
        // CSS für die übrigen Clients bleibt.
        expect(result).toContain('height:auto;width:100%;');
    });

    it('handles any attribute order and escaped ampersands', () => {
        const query = `${src}?v=1&w=2`;
        const html = img(
            `height="auto" width="200" src="${query.replace('&', '&amp;')}"`,
        );

        const result = setOutlookImageHeights(
            html,
            new Map([[query, { width: 400, height: 100 }]]),
        );

        expect(result).toContain('height="50" width="200"');
    });

    it('leaves images alone when the size is unknown or already set', () => {
        const unknown = img(
            'src="https://other.example/a.png" width="430" height="auto"',
        );
        const fixed = img(`src="${src}" width="430" height="200"`);

        expect(setOutlookImageHeights(unknown, sizes)).toBe(unknown);
        expect(setOutlookImageHeights(fixed, sizes)).toBe(fixed);
    });

    it('keeps the rest of the document untouched', () => {
        const html = `<!--[if mso | IE]><table><tr><td><![endif]--><p>Hallo</p>${img(`src="${src}" width="100" height="auto"`)}`;

        expect(setOutlookImageHeights(html, sizes)).toBe(
            html.replace('height="auto"', 'height="130"'),
        );
    });
});

describe('naturalImageSizes', () => {
    it('collects loaded images by their src attribute', () => {
        document.body.innerHTML = `<img src="${src}"><img src="https://shop.example/leer.png">`;
        const [loaded, pending] = Array.from(document.images);
        Object.defineProperty(loaded, 'naturalWidth', { value: 1000 });
        Object.defineProperty(loaded, 'naturalHeight', { value: 1300 });
        Object.defineProperty(pending, 'naturalWidth', { value: 0 });

        expect(naturalImageSizes(document)).toEqual(
            new Map([[src, { width: 1000, height: 1300 }]]),
        );
        expect(naturalImageSizes(null).size).toBe(0);
    });
});
