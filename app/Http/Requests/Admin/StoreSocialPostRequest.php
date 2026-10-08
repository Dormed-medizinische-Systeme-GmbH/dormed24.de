<?php

namespace App\Http\Requests\Admin;

use App\Models\SocialPost;
use App\Support\Social\SocialPostStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSocialPostRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:'.SocialPost::MAX_CONTENT_LENGTH],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['distinct', Rule::in(array_keys(SocialPost::PLATFORMS))],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'platforms.required' => 'Bitte mindestens eine Plattform wählen.',
            'scheduled_at.after' => 'Der Termin muss in der Zukunft liegen.',
        ];
    }

    /**
     * Mit Termin ist der Beitrag geplant, ohne bleibt er Entwurf.
     *
     * @return array{title: string, content: string, platforms: list<string>, scheduled_at: string|null, status: SocialPostStatus}
     */
    public function postData(): array
    {
        /** @var array{title: string, content: string, platforms: array<array-key, string>, scheduled_at?: string|null} $data */
        $data = $this->validated();
        $scheduledAt = $data['scheduled_at'] ?? null;

        return [
            'title' => $data['title'],
            'content' => $data['content'],
            'platforms' => array_values($data['platforms']),
            'scheduled_at' => $scheduledAt,
            'status' => $scheduledAt ? SocialPostStatus::Scheduled : SocialPostStatus::Draft,
        ];
    }
}
