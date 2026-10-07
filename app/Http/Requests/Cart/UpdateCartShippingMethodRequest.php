<?php

namespace App\Http\Requests\Cart;

use App\Models\ShippingMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCartShippingMethodRequest extends FormRequest
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
            'shipping_method' => [
                'required',
                'string',
                Rule::in(ShippingMethod::pluck('id')->map(fn ($id) => (string) $id)->all()),
            ],
        ];
    }
}
