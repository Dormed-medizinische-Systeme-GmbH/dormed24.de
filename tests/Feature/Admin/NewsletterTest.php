<?php

namespace Tests\Feature\Admin;

use App\Mail\NewsletterMail;
use App\Models\Customer;
use App\Models\Newsletter;
use App\Support\Newsletter\NewsletterStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Newsletter Phase 1: Entwürfe im Editor bauen und als Testmail an die
 * Shop-Adresse schicken.
 */
class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    public function test_newsletter_pages_require_the_admin(): void
    {
        $newsletter = Newsletter::factory()->create();

        $this->get(route('admin.newsletters.index'))->assertRedirect(route('admin.login'));
        $this->actingAs(Customer::factory()->create())
            ->get(route('admin.newsletters.edit', $newsletter))
            ->assertRedirect(route('admin.login'));
        $this->actingAs(Customer::factory()->create())
            ->get(route('admin.newsletters.content', $newsletter))
            ->assertRedirect(route('admin.login'));
        $this->actingAs(Customer::factory()->create())
            ->postJson(route('admin.newsletters.test', $newsletter))
            ->assertRedirect(route('admin.login'));
    }

    public function test_index_lists_newsletters(): void
    {
        Newsletter::factory()->create(['name' => 'Herbstaktion']);

        $this->actingAsAdmin()
            ->get(route('admin.newsletters.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Newsletters/Index')
                ->where('newsletters.0.name', 'Herbstaktion')
                ->where('newsletters.0.status', 'draft'));
    }

    public function test_creating_a_newsletter_opens_its_form_with_a_draft(): void
    {
        $response = $this->actingAsAdmin()->post(route('admin.newsletters.store'));

        $newsletter = Newsletter::query()->sole();
        $response->assertRedirect(route('admin.newsletters.edit', $newsletter));
        $this->assertSame(NewsletterStatus::Draft, $newsletter->status);
    }

    public function test_form_page_shows_the_settings_and_the_saved_html(): void
    {
        config(['shop.contact.email' => 'shop@dormed.example']);
        $newsletter = Newsletter::factory()->withContent()->create([
            'subject' => 'Herbstaktion',
            'preheader' => 'Nur im Oktober',
        ]);

        $this->actingAsAdmin()
            ->get(route('admin.newsletters.edit', $newsletter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Newsletters/Edit')
                ->where('newsletter.id', $newsletter->id)
                ->where('newsletter.subject', 'Herbstaktion')
                ->where('newsletter.preheader', 'Nur im Oktober')
                ->where('newsletter.html', $newsletter->html)
                ->where('newsletter.editable', true)
                ->where('newsletter.sent_at', null)
                ->missing('newsletter.design_json')
                ->where('testRecipient', 'shop@dormed.example'));
    }

    public function test_content_page_opens_the_editor_with_the_design(): void
    {
        $newsletter = Newsletter::factory()->withContent()->create();

        $this->actingAsAdmin()
            ->get(route('admin.newsletters.content', $newsletter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Newsletters/Content')
                ->where('newsletter.id', $newsletter->id)
                ->where('newsletter.name', $newsletter->name)
                ->where('newsletter.design_json', $newsletter->design_json)
                ->where('newsletter.editable', true)
                ->missing('newsletter.html'));
    }

    public function test_form_save_redirects_back_with_a_message(): void
    {
        $newsletter = Newsletter::factory()->create();

        $this->actingAsAdmin()
            ->from(route('admin.newsletters.edit', $newsletter))
            ->put(route('admin.newsletters.update', $newsletter), [
                'name' => 'Herbst intern',
                'subject' => 'Neu im Herbst',
                'preheader' => null,
            ])
            ->assertRedirect(route('admin.newsletters.edit', $newsletter))
            ->assertSessionHas('success', 'Newsletter gespeichert.');

        $newsletter->refresh();
        $this->assertSame('Herbst intern', $newsletter->name);
        $this->assertSame('Neu im Herbst', $newsletter->subject);
        $this->assertNull($newsletter->preheader);
    }

    public function test_form_save_shows_validation_errors(): void
    {
        $newsletter = Newsletter::factory()->create();

        $this->actingAsAdmin()
            ->from(route('admin.newsletters.edit', $newsletter))
            ->put(route('admin.newsletters.update', $newsletter), ['subject' => ''])
            ->assertRedirect(route('admin.newsletters.edit', $newsletter))
            ->assertSessionHasErrors('subject');
    }

    public function test_editor_content_is_saved_and_html_is_sanitized(): void
    {
        $newsletter = Newsletter::factory()->create();
        $design = ['pages' => [['frames' => [['component' => ['type' => 'mjml']]]]]];

        $this->actingAsAdmin()
            ->putJson(route('admin.newsletters.update', $newsletter), [
                'subject' => 'Neue Ultraschallgeräte',
                'preheader' => 'Jetzt im Shop',
                'design_json' => $design,
                'mjml' => '<mjml><mj-body></mj-body></mjml>',
                'html' => '<html><body><p onclick="steal()">Hallo</p><script>alert(1)</script><a href="javascript:alert(1)">x</a></body></html>',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Gespeichert.');

        $newsletter->refresh();
        $this->assertSame('Neue Ultraschallgeräte', $newsletter->subject);
        $this->assertSame($design, $newsletter->design_json);
        $this->assertStringContainsString('Hallo', (string) $newsletter->html);
        $this->assertStringNotContainsString('<script', (string) $newsletter->html);
        $this->assertStringNotContainsString('onclick', (string) $newsletter->html);
        $this->assertStringNotContainsString('javascript:', (string) $newsletter->html);
    }

    public function test_save_is_validated(): void
    {
        $newsletter = Newsletter::factory()->create();

        $this->actingAsAdmin()
            ->putJson(route('admin.newsletters.update', $newsletter), [
                'subject' => '',
                'preheader' => str_repeat('x', 201),
                'html' => str_repeat('x', 1_000_001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subject', 'preheader', 'html']);
    }

    public function test_sent_newsletters_can_neither_be_edited_nor_deleted(): void
    {
        $newsletter = Newsletter::factory()->withContent()->create();
        $newsletter->forceFill(['status' => 'sent', 'sent_at' => now()])->save();

        $this->actingAsAdmin()
            ->putJson(route('admin.newsletters.update', $newsletter), ['subject' => 'Geändert'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('newsletter');

        $this->actingAsAdmin()->delete(route('admin.newsletters.destroy', $newsletter));

        $this->assertModelExists($newsletter);
        $this->assertNotSame('Geändert', $newsletter->fresh()->subject);
    }

    public function test_draft_can_be_deleted(): void
    {
        $newsletter = Newsletter::factory()->create();

        $this->actingAsAdmin()
            ->delete(route('admin.newsletters.destroy', $newsletter))
            ->assertRedirect(route('admin.newsletters.index'));

        $this->assertModelMissing($newsletter);
    }

    public function test_test_mail_goes_to_the_shop_address_only(): void
    {
        Mail::fake();
        config(['shop.contact.email' => 'shop@dormed.example']);
        $newsletter = Newsletter::factory()->withContent()->create(['subject' => 'Herbstaktion']);

        $this->actingAsAdmin()
            ->postJson(route('admin.newsletters.test', $newsletter))
            ->assertOk()
            ->assertJsonPath('message', 'Testmail an shop@dormed.example verschickt.');

        Mail::assertSent(NewsletterMail::class, function (NewsletterMail $mail): bool {
            return $mail->hasTo('shop@dormed.example')
                && count($mail->to) === 1
                && $mail->hasSubject('[Test] Herbstaktion');
        });
    }

    public function test_test_mail_renders_the_saved_html(): void
    {
        $newsletter = Newsletter::factory()->withContent()->create();

        (new NewsletterMail($newsletter, isTest: true))->assertSeeInHtml('Hallo Praxis');
    }

    public function test_test_mail_needs_content_and_a_shop_address(): void
    {
        Mail::fake();
        $empty = Newsletter::factory()->create();

        config(['shop.contact.email' => 'shop@dormed.example']);
        $this->actingAsAdmin()
            ->postJson(route('admin.newsletters.test', $empty))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Der Newsletter ist noch leer. Bitte zuerst speichern.');

        config(['shop.contact.email' => '']);
        $this->actingAsAdmin()
            ->postJson(route('admin.newsletters.test', Newsletter::factory()->withContent()->create()))
            ->assertUnprocessable();

        Mail::assertNothingSent();
    }

    public function test_images_are_uploaded_to_the_public_disk(): void
    {
        Storage::fake('public');

        $response = $this->actingAsAdmin()
            ->post(route('admin.newsletters.assets.store'), [
                'files' => [UploadedFile::fake()->image('praxis.jpg', 600, 400)],
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $src = $response->json('data.0.src');
        $this->assertStringContainsString('/storage/newsletters/', $src);
        $this->assertCount(1, Storage::disk('public')->files('newsletters'));
    }

    public function test_only_images_can_be_uploaded(): void
    {
        Storage::fake('public');

        $this->actingAsAdmin()
            ->postJson(route('admin.newsletters.assets.store'), [
                'files' => [UploadedFile::fake()->create('rechnung.pdf', 100, 'application/pdf')],
            ])
            ->assertUnprocessable();

        $this->assertCount(0, Storage::disk('public')->files('newsletters'));
    }
}
