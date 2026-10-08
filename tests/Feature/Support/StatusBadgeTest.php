<?php

namespace Tests\Feature\Support;

use App\Models\Article;
use App\Models\Newsletter;
use App\Models\SocialPost;
use App\Support\Magazine\ArticleStatus;
use App\Support\Newsletter\NewsletterStatus;
use App\Support\Orders\OrderStatus;
use App\Support\Social\SocialPostStatus;
use App\Support\StatusTone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Status-Labels und -Töne kommen aus den PHP-Enums; das Frontend bekommt
 * sie als Prop und pflegt keine eigene Kopie.
 */
class StatusBadgeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string}>
     */
    public static function statusEnums(): array
    {
        return [
            'order' => [OrderStatus::class],
            'article' => [ArticleStatus::class],
            'social post' => [SocialPostStatus::class],
            'newsletter' => [NewsletterStatus::class],
        ];
    }

    /**
     * @param  class-string<OrderStatus|ArticleStatus|SocialPostStatus|NewsletterStatus>  $enum
     */
    #[DataProvider('statusEnums')]
    public function test_every_case_has_a_label_and_a_known_tone(string $enum): void
    {
        $badges = $enum::badges();

        $this->assertSame(array_map(fn ($case) => $case->value, $enum::cases()), array_keys($badges));

        foreach ($badges as $badge) {
            $this->assertNotSame('', $badge['label']);
            $this->assertNotNull(StatusTone::tryFrom($badge['tone']));
        }
    }

    public function test_order_statuses(): void
    {
        $this->assertSame([
            'pending' => ['label' => 'Ausstehend', 'tone' => 'warning'],
            'paid' => ['label' => 'Bezahlt', 'tone' => 'success'],
            'failed' => ['label' => 'Fehlgeschlagen', 'tone' => 'danger'],
            'cancelled' => ['label' => 'Storniert', 'tone' => 'danger'],
            'refunded' => ['label' => 'Erstattet', 'tone' => 'danger'],
        ], OrderStatus::badges());
    }

    public function test_order_statuses_are_shared_with_every_page(): void
    {
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('orderStatuses.paid.label', 'Bezahlt'));
    }

    public function test_marketing_pages_get_their_statuses(): void
    {
        $article = Article::factory()->create();
        $post = SocialPost::factory()->create();
        $newsletter = Newsletter::factory()->create();

        foreach ([
            route('admin.articles.index') => 'Veröffentlicht',
            route('admin.articles.edit', $article) => 'Veröffentlicht',
            route('admin.social-posts.index') => 'Fehlgeschlagen',
            route('admin.social-posts.edit', $post) => 'Fehlgeschlagen',
            route('admin.newsletters.index') => 'Verschickt',
            route('admin.newsletters.edit', $newsletter) => 'Verschickt',
        ] as $url => $label) {
            $this->actingAsAdmin()
                ->get($url)
                ->assertInertia(fn (Assert $page) => $page->where(
                    'statuses',
                    fn ($statuses) => collect($statuses)->pluck('label')->contains($label),
                ));
        }
    }
}
