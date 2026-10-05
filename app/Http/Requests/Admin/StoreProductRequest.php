<?php

namespace App\Http\Requests\Admin;

use App\Support\Catalog\Categories;
use App\Support\Catalog\Specialties;
use Closure;
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
     * Komplett leere Zeilen aus dem Admin-Formular werden verworfen,
     * Werte getrimmt — so muss niemand leere Zeilen vor dem Speichern entfernen.
     */
    protected function prepareForValidation(): void
    {
        $specs = $this->input('technical_specs');

        if (! is_array($specs)) {
            return;
        }

        $this->merge([
            'technical_specs' => array_values(array_filter(
                array_map(
                    fn (mixed $row): mixed => is_array($row)
                        ? array_map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value, $row)
                        : $row,
                    $specs,
                ),
                fn (mixed $row): bool => ! is_array($row) || filled($row['label'] ?? null) || filled($row['value'] ?? null),
            )),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'technical_specs' => ['nullable', 'array', 'max:50'],
            'technical_specs.*' => ['array:label,value'],
            'technical_specs.*.label' => ['required', 'string', 'max:255'],
            'technical_specs.*.value' => ['required', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0'],
            'manufacturer_id' => ['nullable', 'integer', 'exists:manufacturers,id'],
            'category_slug' => ['nullable', 'string', Rule::in(Categories::slugs())],
            'specialties' => [
                'nullable',
                'array',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_array($value) && $value !== [] && ! $this->categoryHasSpecialties()) {
                        $fail('Fachrichtungen gibt es nur für: '.$this->specialtyCategoryNames().'.');
                    }
                },
            ],
            'specialties.*' => ['string', 'distinct', Rule::in(Specialties::slugs())],
            'is_available' => ['required', 'boolean'],
        ];
    }

    /**
     * Validierte Produktdaten. Fachrichtungen werden in Config-Reihenfolge
     * gespeichert und geleert, sobald die Kategorie keine (mehr) vorsieht.
     *
     * @return array<string, mixed>
     */
    public function productData(): array
    {
        $data = $this->validated();

        if (! $this->categoryHasSpecialties()) {
            $data['specialties'] = null;
        } elseif (array_key_exists('specialties', $data)) {
            $data['specialties'] = Specialties::normalize((array) ($data['specialties'] ?? []));
        }

        return $data;
    }

    private function specialtyCategoryNames(): string
    {
        return implode(', ', array_map(
            fn (string $slug): string => Categories::find($slug)->name ?? $slug,
            Categories::slugsWithSpecialties(),
        ));
    }

    private function categoryHasSpecialties(): bool
    {
        return in_array($this->input('category_slug'), Categories::slugsWithSpecialties(), true);
    }
}
