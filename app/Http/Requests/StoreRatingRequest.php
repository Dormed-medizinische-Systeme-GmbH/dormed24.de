<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRatingRequest extends FormRequest
{
    /**
     * Nur wer das Produkt mit einer bezahlten Bestellung gekauft hat.
     */
    public function authorize(): bool
    {
        $customer = $this->user();

        return $customer instanceof Customer
            && $customer->paidOrderContaining($this->product()) !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Customer $customer */
                $customer = $this->user();

                if ($customer->hasRated($this->product())) {
                    $validator->errors()->add('rating', 'Sie haben dieses Produkt bereits bewertet.');
                }
            },
        ];
    }

    public function product(): Product
    {
        /** @var Product $product */
        $product = $this->route('product');

        return $product;
    }
}
