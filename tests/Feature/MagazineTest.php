<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Öffentliches Magazin unter /magazin.
 */
class MagazineTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_only_published_articles_newest_first(): void
    {
        Article::factory()->create(['title' => 'Entwurf']);
        Article::factory()->scheduled()->create(['title' => 'Geplant']);
        Article::factory()->create(['title' => 'Älter', 'published_at' => now()->subDays(3)]);
        Article::factory()->create(['title' => 'Neuer', 'published_at' => now()->subDay()]);

        $this->get(route('magazine.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Magazine/Index')
                ->has('articles', 2)
                ->where('articles.0.title', 'Neuer')
                ->where('articles.1.title', 'Älter')
                ->where('activeCategory', null));
    }

    public function test_index_filters_by_category_and_ignores_unknown_ones(): void
    {
        Article::factory()->published()->create(['category' => 'ratgeber']);
        Article::factory()->published()->create(['category' => 'aktuelles', 'title' => 'News']);

        $this->get(route('magazine.index', ['kategorie' => 'aktuelles']))
            ->assertInertia(fn ($page) => $page
                ->has('articles', 1)
                ->where('articles.0.title', 'News')
                ->where('articles.0.category', 'Aktuelles')
                ->where('activeCategory', 'aktuelles'));

        $this->get(route('magazine.index', ['kategorie' => 'gibtsnicht']))
            ->assertInertia(fn ($page) => $page->has('articles', 2)->where('activeCategory', null));
    }

    public function test_published_article_is_shown_with_sanitized_html(): void
    {
        $article = Article::factory()->published()->create([
            'slug' => 'sonden-reinigen',
            'content' => "## Pflege\n\nText mit **fett**.\n\n<script>alert(1)</script>\n\n[gefährlich](javascript:alert(1)) und [sicher](https://dormed24.de)",
        ]);

        $this->get(route('magazine.show', $article))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Magazine/Show')
                ->where('article.title', $article->title)
                ->where('article.html', fn (string $html) => str_contains($html, '<h2>Pflege</h2>')
                    && str_contains($html, '<strong>fett</strong>')
                    && str_contains($html, 'href="https://dormed24.de"')
                    && ! str_contains($html, '<script')
                    && ! str_contains($html, 'javascript:')));
    }

    public function test_drafts_and_scheduled_articles_are_not_found(): void
    {
        $draft = Article::factory()->create();
        $scheduled = Article::factory()->scheduled()->create();

        $this->get(route('magazine.show', $draft))->assertNotFound();
        $this->get(route('magazine.show', $scheduled))->assertNotFound();
        $this->get('/magazin/gibt-es-nicht')->assertNotFound();
    }

    public function test_magazine_is_not_listed_in_the_sitemap(): void
    {
        Article::factory()->published()->create(['slug' => 'sichtbar']);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertDontSee('/magazin');
    }
}
