import { describe, expect, it } from 'vitest';
import { illegalAttributes, removeIllegalAttributes } from './mjml-cleanup';

describe('illegalAttributes', () => {
    it('reads the attribute names per tag from MJML errors', () => {
        const result = illegalAttributes([
            {
                tagName: 'mj-hero',
                message: 'Attributes color, border are illegal',
            },
            { tagName: 'mj-text', message: 'Attribute foo is illegal' },
            { tagName: 'mj-hero', message: 'Attribute color is illegal' },
            {
                tagName: 'mj-column',
                message: 'mj-column cannot be used inside mj-body',
            },
        ]);

        expect(result).toEqual(
            new Map([
                ['mj-hero', ['color', 'border']],
                ['mj-text', ['foo']],
            ]),
        );
    });
});

describe('removeIllegalAttributes', () => {
    it('removes the attributes from every tag of that type only', () => {
        const mjml =
            '<mjml><mj-body><mj-hero color="#fff" mode="fluid-height" border="1px solid red"><mj-text color="#fff">Hallo</mj-text></mj-hero>' +
            '<mj-hero border="0">Zwei</mj-hero></mj-body></mjml>';

        const result = removeIllegalAttributes(
            mjml,
            new Map([['mj-hero', ['color', 'border']]]),
        );

        expect(result).toBe(
            '<mjml><mj-body><mj-hero mode="fluid-height"><mj-text color="#fff">Hallo</mj-text></mj-hero>' +
                '<mj-hero>Zwei</mj-hero></mj-body></mjml>',
        );
    });

    it('does not touch similar attribute names or other tags', () => {
        const mjml =
            '<mj-hero border-radius="4px" background-color="#000"></mj-hero><mj-hero-content color="x">';

        expect(
            removeIllegalAttributes(
                mjml,
                new Map([['mj-hero', ['border', 'color']]]),
            ),
        ).toBe(mjml);
    });
});
