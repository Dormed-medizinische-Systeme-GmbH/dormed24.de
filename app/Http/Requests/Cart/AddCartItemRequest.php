<?php

namespace App\Http\Requests\Cart;

use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AddCartItemRequest extends FormRequest
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
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_available', true)],
            'variant_id' => [
                'nullable',
                'integer',
                Rule::exists('product_variants', 'id')->where('product_id', $this->integer('product_id')),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    /**
     * Products that define variants must be bought as a concrete variant,
     * otherwise the price would be ambiguous.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->filled('variant_id')) {
                    return;
                }

                $hasVariants = ProductVariant::query()
                    ->where('product_id', $this->integer('product_id'))
                    ->exists();

                if ($hasVariants) {
                    $validator->errors()->add('variant_id', 'Bitte wählen Sie eine Variante aus.');
                }
            },
        ];
    }
}
