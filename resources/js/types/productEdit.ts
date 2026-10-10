import type { Snippet } from 'svelte';

export type ProductImage = {
    id: number;
    path: string;
    sort_order: number;
    url: string;
};

export type ProductVariant = {
    id: number;
    label: string;
    price: string;
    sort_order: number;
    is_default: boolean;
};

export type ProductEditProduct = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    technical_specs: { label: string; value: string }[] | null;
    price: string;
    manufacturer_id: number | null;
    category_slug: string | null;
    specialties: string[] | null;
    is_available: boolean;
    images: ProductImage[];
    variants: ProductVariant[];
};

export type ProductRating = {
    id: number;
    stars: number;
    content: string;
    created_at: string | null;
    verified: boolean;
};

export type Manufacturer = { id: number; name: string };
export type Category = { slug: string; name: string };
export type Specialty = { slug: string; name: string };

/**
 * Die Formularfelder der Produktmaske als Snippets. `Edit.svelte` besitzt das
 * Formular und rendert jedes Feld; das Layout ordnet sie nur an.
 */
export type ProductEditFields = {
    name: Snippet;
    price: Snippet;
    category: Snippet;
    specialties: Snippet;
    manufacturer: Snippet;
    description: Snippet;
    specs: Snippet;
    availability: Snippet;
};

/** Props der Produktmaske. */
export type ProductEditLayoutProps = {
    product: ProductEditProduct;
    ratings: ProductRating[];
    fields: ProductEditFields;
    onsubmit: (event: SubmitEvent) => void;
};
