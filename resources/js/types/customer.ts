export type CustomerAddress = {
    id: number;
    type: string;
    company: string | null;
    salutation: string | null;
    first_name: string;
    last_name: string;
    street: string;
    house_number: string;
    address_line2: string | null;
    zip: string;
    city: string;
    country: string;
    phone: string | null;
    is_default: boolean;
};

export type CustomerOrderItem = {
    product_name: string;
    unit_price: string;
    quantity: number;
};

export type CustomerOrder = {
    id: number;
    status: string;
    total_amount: string;
    created_at: string;
    items: CustomerOrderItem[];
};

export type CustomerDetail = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    addresses: CustomerAddress[];
    orders: CustomerOrder[];
};
