<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Öffentliches Magazin. Noch nicht verlinkt und auf noindex gestellt.
 */
class MagazineController extends Controller
{
    public function index(Request $request): Response
    {
        $category = $request->string('kategorie')->toString();
        $category = array_key_exists($category, Article::CATEGORIES) ? $category : null;

        $articles = Article::query()
            ->published()
            ->when($category, fn ($query) => $query->where('category', $category))
            ->latest('published_at')
            ->get()
            ->map(fn (Article $article): array => [
                'title' => $article->title,
                'slug' => $article->slug,
                'teaser' => $article->teaser,
                'category' => $article->categoryLabel(),
                'published_at' => $article->published_at?->toIso8601String(),
            ]);

        return Inertia::render('Magazine/Index', [
            'articles' => $articles,
            'categories' => Article::CATEGORIES,
            'activeCategory' => $category,
        ]);
    }

    public function show(Article $article): Response
    {
        abort_unless($article->status() === Article::STATUS_PUBLISHED, 404);

        return Inertia::render('Magazine/Show', [
            'article' => [
                'title' => $article->title,
                'teaser' => $article->teaser,
                'category' => $article->categoryLabel(),
                'published_at' => $article->published_at?->toIso8601String(),
                'html' => $article->contentHtml(),
            ],
        ]);
    }
}
