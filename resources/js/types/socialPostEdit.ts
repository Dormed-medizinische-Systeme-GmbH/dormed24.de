import type { Snippet } from 'svelte';

/** Die Felder der Social-Media-Maske als Snippets; `SocialPostFields` besitzt die Bindungen. */
export type SocialPostEditFields = {
    title: Snippet;
    /** `true` = großes Composer-Feld ohne sichtbares Label. */
    content: Snippet<[boolean?]>;
    /** `'pills'` = Plattformen als Auswahl-Chips statt Checkboxen. */
    platforms: Snippet<[('checkboxes' | 'pills')?]>;
    scheduledAt: Snippet;
    /** Gesperrte Platzhalter für später: Medien, Link, Hashtags, Verknüpfung, Plattform-Texte. */
    media: Snippet;
    link: Snippet;
    hashtags: Snippet;
    linked: Snippet;
    platformTexts: Snippet;
};

export type SocialPostEditLayoutProps = {
    fields: SocialPostEditFields;
    errors: Partial<Record<string, string>>;
};

/** Live-Werte des (ungespeicherten) Beitrags für die Plattform-Vorschau. */
export type SocialPostEditPreview = {
    content: string;
    platforms: string[];
    platformLabels: Record<string, string>;
    maxContentLength: number;
};
