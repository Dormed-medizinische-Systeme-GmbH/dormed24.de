<?php

namespace App\Http\Requests\Admin;

use App\Support\Catalog\Categories;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'manufacturer_id' => ['nullable', 'integer', 'exists:manufacturers,id'],
            'category_slug' => ['nullable', 'string', Rule::in(Categories::slugs())],
            'is_available' => ['required', 'boolean'],
        ];
    }
}
