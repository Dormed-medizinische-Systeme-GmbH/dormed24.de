import type { Auth } from '@/types/auth';
import type { Cart } from '@/types/cart';

type ContactInfo = {
    email: string;
    phone: string;
    fax: string;
    phone_href: string;
    fax_href: string;
};

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(
            pattern: string,
            options?: { eager?: boolean },
        ) => Record<string, T>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            appUrl: string;
            auth: Auth;
            cart: Cart;
            contact: ContactInfo;
            sidebarOpen: boolean;
            sandbox: boolean;
            [key: string]: unknown;
        };
    }
}
