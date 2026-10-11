<?php

namespace App\Http\Requests\Checkout;

use App\Support\Orders\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Klick auf einen der PayPal-Buttons im Checkout; "funding_source" ist die
 * gewählte Zahlart (paypal, card, sepa).
 */
class CreatePayPalOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'agreed_to_terms' => ['accepted'],
            'funding_source' => [
                'required',
                'string',
                Rule::in(array_map(fn (PaymentMethod $method): string => $method->value, PaymentMethod::payPalFunded())),
            ],
        ];
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from($this->string('funding_source')->toString());
    }
}
