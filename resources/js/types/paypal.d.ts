/**
 * Minimal ambient typing for the PayPal JS SDK, which attaches a global
 * `paypal` object once the SDK script has loaded at runtime
 * (see components/PayPalButtons.svelte).
 */
type PayPalButtonActions = {
    enable: () => void;
    disable: () => void;
};

type PayPalClickActions = {
    resolve: () => Promise<void>;
    reject: () => Promise<void>;
};

type PayPalButtonsConfig = {
    fundingSource?: string;
    style?: Record<string, string | number>;
    onInit?: (data: unknown, actions: PayPalButtonActions) => void;
    onClick?: (data: unknown, actions: PayPalClickActions) => Promise<void>;
    createOrder?: () => Promise<string>;
    onApprove?: (data: { orderID: string }) => Promise<void> | void;
    onCancel?: () => void;
    onError?: (error: unknown) => void;
};

type PayPalButtonsInstance = {
    isEligible: () => boolean;
    render: (container: string | HTMLElement) => void;
};

type PayPalNamespace = {
    Buttons: (config: PayPalButtonsConfig) => PayPalButtonsInstance;
};

declare const paypal: PayPalNamespace | undefined;
