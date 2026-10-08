/**
 * Startvorlage für neue Newsletter: Kopf, Text, Button, Fuß in den
 * Shop-Farben. Schrift und Farben stehen direkt an den Elementen:
 * grapesjs-mjml stellt <mj-attributes> im Editor als sichtbare Blöcke dar.
 */
export const FONT_FAMILY = 'Helvetica Neue, Helvetica, Arial, sans-serif';

export const SHOP_COLORS = {
    primary: '#1a3a5c',
    accent: '#1a6bbf',
    text: '#374151',
    muted: '#6b7280',
    background: '#f5f5f5',
} as const;

export function starterTemplate(shopUrl: string): string {
    return `<mjml>
  <mj-body background-color="${SHOP_COLORS.background}" width="600px">
    <mj-section background-color="${SHOP_COLORS.primary}" padding="20px">
      <mj-column>
        <mj-text font-family="${FONT_FAMILY}" align="center" color="#ffffff" font-size="22px" font-weight="700">dormed24</mj-text>
      </mj-column>
    </mj-section>
    <mj-section background-color="#ffffff" padding="24px">
      <mj-column>
        <mj-text font-family="${FONT_FAMILY}" font-size="20px" font-weight="700" color="${SHOP_COLORS.primary}">Überschrift</mj-text>
        <mj-text font-family="${FONT_FAMILY}" font-size="15px" line-height="1.6" color="${SHOP_COLORS.text}">Hier steht Ihr Text. Klicken Sie auf einen Block, um ihn zu bearbeiten, oder ziehen Sie neue Blöcke aus der Leiste links hinein.</mj-text>
        <mj-button font-family="${FONT_FAMILY}" href="${shopUrl}" background-color="${SHOP_COLORS.accent}" color="#ffffff" border-radius="6px" font-weight="600">Zum Shop</mj-button>
      </mj-column>
    </mj-section>
    <mj-section background-color="#ffffff" padding="16px">
      <mj-column>
        <mj-text font-family="${FONT_FAMILY}" align="center" font-size="12px" color="${SHOP_COLORS.muted}">dormed medizinische Systeme GmbH</mj-text>
      </mj-column>
    </mj-section>
  </mj-body>
</mjml>`;
}

/**
 * Ersatz für den Titelbild-Block von grapesjs-mjml: der lädt ein
 * Beispielbild von GitHub. Hier Shop-Farben ohne fremdes Bild; ein
 * Hintergrundbild lässt sich im Stil-Tab setzen.
 */
export function heroBlock(shopUrl: string): string {
    return `<mj-hero mode="fluid-height" background-color="${SHOP_COLORS.primary}" padding="60px 24px">
  <mj-text font-family="${FONT_FAMILY}" align="center" color="#ffffff" font-size="32px" line-height="1.2" font-weight="700">Ihre Überschrift</mj-text>
  <mj-text font-family="${FONT_FAMILY}" align="center" color="#ffffff" font-size="16px" line-height="1.6">Eine kurze Zeile zum Angebot.</mj-text>
  <mj-button font-family="${FONT_FAMILY}" href="${shopUrl}" background-color="${SHOP_COLORS.accent}" color="#ffffff" border-radius="6px" font-weight="600">Zum Shop</mj-button>
</mj-hero>`;
}
