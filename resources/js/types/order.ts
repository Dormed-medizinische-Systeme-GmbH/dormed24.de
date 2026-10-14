export type OrderItem = {
    id: number;
    product_name: string;
    unit_price: string;
    quantity: number;
    image_url: string | null;
};

export type OrderSummary = {
    subtotal: string;
    shipping_method: string | null;
    shipping_total: string;
    vat_rate: number;
    vat_amount: string;
    total: string;
};

export type OrderPayment = {
    id: number;
    paypal_order_id: string | null;
    status: string;
    amount: string;
    currency: string;
    payer_email: string | null;
    payer_name: string | null;
    created_at: string;
};

export type AddressSnapshot = Record<string, string> | null;

export type OrderDetail = {
    id: number;
    status: string;
    payment_method: string | null;
    total_amount: string;
    shipping_amount: string;
    shipping_address: AddressSnapshot;
    billing_address: AddressSnapshot;
    created_at: string;
    updated_at: string;
    customer: { id: number; name: string; email: string } | null;
    items: OrderItem[];
    payments: OrderPayment[];
    summary: OrderSummary;
};
