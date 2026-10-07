<?php

namespace App\Http\Requests\Cart;

use App\Support\Cart\PaymentOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCartPaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'payment_method' => [
                'required',
                'string',
                Rule::in(array_keys(PaymentOption::configuredLabels())),
            ],
        ];
    }
}
