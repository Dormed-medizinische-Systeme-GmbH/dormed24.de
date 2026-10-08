<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\SocialPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Social-Media-Beiträge: Liste, Anlegen, Bearbeiten, Löschen.
 */
class SocialPostManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_post_pages_require_the_admin(): void
    {
        $post = SocialPost::factory()->create();

        $this->actingAs(Customer::factory()->create())
            ->get(route('admin.social-posts.edit', $post))
            ->assertRedirect(route('admin.login'));
        $this->post(route('admin.social-posts.store'))->assertRedirect(route('admin.login'));
    }

    public function test_index_lists_all_posts_with_platform_labels(): void
    {
        SocialPost::factory()->count(25)->create();
        $this->travel(1)->minutes();
        SocialPost::factory()->scheduled()->create(['title' => 'Messe Düsseldorf']);

        $this->actingAsAdmin()
            ->get(route('admin.social-posts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/SocialPosts/Index')
                ->has('posts', 26)
                ->where('posts.0.title', 'Messe Düsseldorf')
                ->where('posts.0.status', SocialPost::STATUS_SCHEDULED)
                ->where('platforms', SocialPost::PLATFORMS));
    }

    public function test_create_page_offers_the_platforms(): void
    {
        $this->actingAsAdmin()
            ->get(route('admin.social-posts.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/SocialPosts/Create')
                ->where('platforms', SocialPost::PLATFORMS)
                ->where('maxContentLength', SocialPost::MAX_CONTENT_LENGTH));
    }

    public function test_post_without_date_is_stored_as_draft(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.social-posts.store'), [
                'title' => 'Herbstaktion',
                'content' => 'Neue Ultraschallgeräte im Shop.',
                'platforms' => ['linkedin', 'facebook'],
                'scheduled_at' => null,
            ])
            ->assertRedirect(route('admin.social-posts.index'))
            ->assertSessionHas('success');

        $post = SocialPost::query()->sole();
        $this->assertSame(SocialPost::STATUS_DRAFT, $post->status);
        $this->assertSame(['linkedin', 'facebook'], $post->platforms);
        $this->assertNull($post->scheduled_at);
    }

    public function test_post_with_future_date_is_scheduled_in_utc(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.social-posts.store'), [
                'title' => 'Messe',
                'content' => 'Besuchen Sie uns.',
                'platforms' => ['linkedin'],
                'scheduled_at' => '2099-03-01T08:30:00.000Z',
            ])
            ->assertRedirect(route('admin.social-posts.index'));

        $post = SocialPost::query()->sole();
        $this->assertSame(SocialPost::STATUS_SCHEDULED, $post->status);
        $this->assertSame('2099-03-01 08:30:00', $post->scheduled_at?->format('Y-m-d H:i:s'));
    }

    public function test_store_is_validated(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.social-posts.store'), [
                'title' => '',
                'content' => str_repeat('x', SocialPost::MAX_CONTENT_LENGTH + 1),
                'platforms' => ['twitter'],
                'scheduled_at' => now()->subHour()->toIso8601String(),
            ])
            ->assertSessionHasErrors(['title', 'content', 'platforms.0', 'scheduled_at']);

        $this->actingAsAdmin()
            ->post(route('admin.social-posts.store'), [
                'title' => 'Ohne Plattform',
                'content' => 'Text',
                'platforms' => [],
            ])
            ->assertSessionHasErrors('platforms');

        $this->assertDatabaseCount('social_posts', 0);
    }

    public function test_edit_page_shows_the_post(): void
    {
        $post = SocialPost::factory()->scheduled()->create();

        $this->actingAsAdmin()
            ->get(route('admin.social-posts.edit', $post))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/SocialPosts/Edit')
                ->where('post.id', $post->id)
                ->where('post.scheduled_at', $post->scheduled_at?->toIso8601String())
                ->where('post.editable', true));
    }

    public function test_post_can_be_updated_and_unscheduled(): void
    {
        $post = SocialPost::factory()->scheduled()->create();

        $this->actingAsAdmin()
            ->put(route('admin.social-posts.update', $post), [
                'title' => 'Geändert',
                'content' => 'Neuer Text',
                'platforms' => ['facebook'],
                'scheduled_at' => null,
            ])
            ->assertRedirect(route('admin.social-posts.index'));

        $post->refresh();
        $this->assertSame('Geändert', $post->title);
        $this->assertSame(['facebook'], $post->platforms);
        $this->assertSame(SocialPost::STATUS_DRAFT, $post->status);
        $this->assertNull($post->scheduled_at);
    }

    public function test_published_posts_cannot_be_changed(): void
    {
        $post = SocialPost::factory()->published()->create(['title' => 'Alt']);

        $this->actingAsAdmin()
            ->put(route('admin.social-posts.update', $post), [
                'title' => 'Neu',
                'content' => 'Text',
                'platforms' => ['linkedin'],
            ])
            ->assertSessionHas('error');

        $this->assertSame('Alt', $post->fresh()?->title);
        $this->assertSame(SocialPost::STATUS_PUBLISHED, $post->fresh()?->status);
    }

    public function test_post_can_be_deleted(): void
    {
        $post = SocialPost::factory()->create();

        $this->actingAsAdmin()
            ->delete(route('admin.social-posts.destroy', $post))
            ->assertRedirect(route('admin.social-posts.index'));

        $this->assertModelMissing($post);
    }
}
