<?php

namespace App\Http\Requests\Admin;

use App\Models\Newsletter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateNewsletterRequest extends FormRequest
{
    /**
     * Obergrenze für Editor-Daten und HTML, damit ein Request nicht beliebig
     * groß werden kann (1 MB je Feld).
     */
    private const int MAX_CONTENT_LENGTH = 1_000_000;

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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'subject' => ['sometimes', 'required', 'string', 'max:150'],
            'preheader' => ['sometimes', 'nullable', 'string', 'max:200'],
            'design_json' => ['sometimes', 'nullable', 'array'],
            'mjml' => ['sometimes', 'nullable', 'string', 'max:'.self::MAX_CONTENT_LENGTH],
            'html' => ['sometimes', 'nullable', 'string', 'max:'.self::MAX_CONTENT_LENGTH],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $newsletter = $this->route('newsletter');

                if ($newsletter instanceof Newsletter && ! $newsletter->isEditable()) {
                    $validator->errors()->add('newsletter', 'Verschickte Newsletter können nicht mehr bearbeitet werden.');
                }

                if ($this->has('design_json') && strlen((string) json_encode($this->input('design_json'))) > self::MAX_CONTENT_LENGTH) {
                    $validator->errors()->add('design_json', 'Der Newsletter ist zu groß.');
                }
            },
        ];
    }
}
