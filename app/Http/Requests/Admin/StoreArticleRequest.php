<?php

namespace App\Http\Requests\Admin;

use App\Models\Article;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArticleRequest extends FormRequest
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
        $article = $this->route('article');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('articles', 'slug')->ignore($article instanceof Article ? $article->id : null),
            ],
            'category' => ['required', Rule::in(array_keys(Article::CATEGORIES))],
            'teaser' => ['nullable', 'string', 'max:300'],
            'content' => ['required', 'string', 'max:100000'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Nur Kleinbuchstaben, Ziffern und Bindestriche.',
            'slug.unique' => 'Diese Adresse ist schon vergeben.',
        ];
    }

    /**
     * Ohne Slug wird er aus dem Titel erzeugt.
     *
     * @return array{title: string, slug: string, category: string, teaser: string|null, content: string, published_at: string|null}
     */
    public function articleData(?Article $article = null): array
    {
        /** @var array{title: string, slug?: string|null, category: string, teaser?: string|null, content: string, published_at?: string|null} $data */
        $data = $this->validated();

        return [
            'title' => $data['title'],
            'slug' => filled($data['slug'] ?? null)
                ? (string) $data['slug']
                : Article::uniqueSlug($data['title'], $article?->id),
            'category' => $data['category'],
            'teaser' => $data['teaser'] ?? null,
            'content' => $data['content'],
            'published_at' => $data['published_at'] ?? null,
        ];
    }
}
