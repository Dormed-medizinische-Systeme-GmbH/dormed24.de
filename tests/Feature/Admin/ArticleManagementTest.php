<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Customer;
use App\Support\Magazine\ArticleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Magazin-Artikel im Admin: Liste, Anlegen, Bearbeiten, Löschen.
 */
class ArticleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_pages_require_the_admin(): void
    {
        $article = Article::factory()->create();

        $this->get(route('admin.articles.index'))->assertRedirect(route('admin.login'));
        $this->actingAs(Customer::factory()->create())
            ->get(route('admin.articles.edit', $article))
            ->assertRedirect(route('admin.login'));
    }

    public function test_index_lists_articles_with_their_status(): void
    {
        Article::factory()->create(['title' => 'Entwurf']);
        Article::factory()->scheduled()->create(['title' => 'Geplant']);
        $this->travel(1)->minutes();
        Article::factory()->published()->create(['title' => 'Online']);

        $this->actingAsAdmin()
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Articles/Index')
                ->has('articles', 3)
                ->where('articles.0.title', 'Online')
                ->where('articles.0.status', ArticleStatus::Published->value)
                ->where('categories', Article::CATEGORIES));

        $statuses = Article::query()->get()->mapWithKeys(fn (Article $article) => [$article->title => $article->status()]);
        $this->assertSame(ArticleStatus::Draft, $statuses['Entwurf']);
        $this->assertSame(ArticleStatus::Scheduled, $statuses['Geplant']);
    }

    public function test_article_is_created_with_a_slug_from_the_title(): void
    {
        Article::factory()->create(['slug' => 'welches-ultraschallgerat-passt']);

        $this->actingAsAdmin()
            ->post(route('admin.articles.store'), [
                'title' => 'Welches Ultraschallgerät passt?',
                'slug' => '',
                'category' => 'ratgeber',
                'teaser' => 'Kurz erklärt.',
                'content' => "## Start\n\nText",
                'published_at' => null,
            ])
            ->assertSessionHasNoErrors();

        $article = Article::query()->latest('id')->firstOrFail();
        $this->assertSame('welches-ultraschallgerat-passt-2', $article->slug);
        $this->assertSame(ArticleStatus::Draft, $article->status());
    }

    public function test_store_is_validated(): void
    {
        Article::factory()->create(['slug' => 'vergeben']);

        $this->actingAsAdmin()
            ->post(route('admin.articles.store'), [
                'title' => '',
                'slug' => 'Mit Leerzeichen',
                'category' => 'unbekannt',
                'teaser' => str_repeat('x', 301),
                'content' => '',
                'published_at' => 'kein Datum',
            ])
            ->assertSessionHasErrors(['title', 'slug', 'category', 'teaser', 'content', 'published_at']);

        $this->actingAsAdmin()
            ->post(route('admin.articles.store'), [
                'title' => 'Doppelt',
                'slug' => 'vergeben',
                'category' => 'ratgeber',
                'content' => 'Text',
            ])
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseCount('articles', 1);
    }

    public function test_article_can_be_updated_and_keeps_its_own_slug(): void
    {
        $article = Article::factory()->create(['slug' => 'alt']);

        $this->actingAsAdmin()
            ->put(route('admin.articles.update', $article), [
                'title' => 'Neuer Titel',
                'slug' => 'alt',
                'category' => 'aktuelles',
                'teaser' => null,
                'content' => 'Neu',
                'published_at' => '2026-01-01T08:00:00.000Z',
            ])
            ->assertRedirect(route('admin.articles.edit', $article))
            ->assertSessionHasNoErrors();

        $article->refresh();
        $this->assertSame('Neuer Titel', $article->title);
        $this->assertSame('alt', $article->slug);
        $this->assertSame('aktuelles', $article->category);
        $this->assertSame(ArticleStatus::Published, $article->status());
    }

    public function test_edit_page_shows_the_article(): void
    {
        $article = Article::factory()->scheduled()->create();

        $this->actingAsAdmin()
            ->get(route('admin.articles.edit', $article))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Articles/Edit')
                ->where('article.id', $article->id)
                ->where('article.content', $article->content)
                ->where('article.status', ArticleStatus::Scheduled->value));
    }

    public function test_article_can_be_deleted(): void
    {
        $article = Article::factory()->create();

        $this->actingAsAdmin()
            ->delete(route('admin.articles.destroy', $article))
            ->assertRedirect(route('admin.articles.index'));

        $this->assertModelMissing($article);
    }
}
