<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArticleRequest;
use App\Models\Article;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Magazin-Artikel pflegen. Im Shop unter /magazin (noch nicht verlinkt).
 */
class ArticleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Articles/Index', [
            'articles' => Article::query()
                ->latest('updated_at')
                ->get()
                ->map(fn (Article $article): array => [
                    'id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'teaser' => $article->teaser,
                    'category' => $article->category,
                    'status' => $article->status(),
                    'published_at' => $article->published_at?->toIso8601String(),
                    'updated_at' => $article->updated_at->toIso8601String(),
                ]),
            'categories' => Article::CATEGORIES,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Articles/Create', [
            'categories' => Article::CATEGORIES,
        ]);
    }

    public function store(StoreArticleRequest $request): RedirectResponse
    {
        $article = Article::create($request->articleData());

        return to_route('admin.articles.edit', $article)->with('success', 'Artikel angelegt.');
    }

    public function edit(Article $article): Response
    {
        return Inertia::render('Admin/Articles/Edit', [
            'article' => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'category' => $article->category,
                'teaser' => $article->teaser,
                'content' => $article->content,
                'status' => $article->status(),
                'published_at' => $article->published_at?->toIso8601String(),
            ],
            'categories' => Article::CATEGORIES,
        ]);
    }

    public function update(StoreArticleRequest $request, Article $article): RedirectResponse
    {
        $article->update($request->articleData($article));

        return to_route('admin.articles.edit', $article)->with('success', 'Artikel gespeichert.');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $article->delete();

        return to_route('admin.articles.index')->with('success', 'Artikel gelöscht.');
    }
}
