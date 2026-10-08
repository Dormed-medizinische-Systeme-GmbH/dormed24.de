<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateNewsletterRequest;
use App\Mail\NewsletterMail;
use App\Models\Newsletter;
use App\Support\Newsletter\HtmlSanitizer;
use App\Support\Newsletter\NewsletterStatus;
use App\Support\ShopContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Newsletter, Phase 1: Entwürfe im GrapesJS-/MJML-Editor bauen und als
 * Testmail an die Shop-Adresse schicken. Abonnenten und echter Versand
 * kommen in Phase 2 (siehe .todo/newsletter-editor-grapesjs-mjml-laravel-plan.md).
 */
class NewsletterController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Newsletters/Index', [
            'newsletters' => Newsletter::query()
                ->latest('updated_at')
                ->get(['id', 'name', 'subject', 'status', 'sent_at', 'updated_at'])
                ->map(fn (Newsletter $newsletter): array => [
                    'id' => $newsletter->id,
                    'name' => $newsletter->name,
                    'subject' => $newsletter->subject,
                    'status' => $newsletter->status,
                    'updated_at' => $newsletter->updated_at->format('d.m.Y H:i'),
                ]),
            'statuses' => NewsletterStatus::badges(),
        ]);
    }

    public function store(): RedirectResponse
    {
        $newsletter = Newsletter::create([
            'name' => 'Neuer Newsletter',
            'subject' => 'Neuigkeiten von dormed24',
        ]);

        return to_route('admin.newsletters.edit', $newsletter);
    }

    /**
     * Eingabemaske des Newsletters: Betreff, Vorschautext, Vorschau des
     * gespeicherten Inhalts und (ab Phase 2) Versand.
     */
    public function edit(Newsletter $newsletter): Response
    {
        return Inertia::render('Admin/Newsletters/Edit', [
            'newsletter' => [
                'id' => $newsletter->id,
                'name' => $newsletter->name,
                'subject' => $newsletter->subject,
                'preheader' => $newsletter->preheader,
                'status' => $newsletter->status,
                'html' => $newsletter->html,
                'editable' => $newsletter->isEditable(),
                'updated_at' => $newsletter->updated_at->format('d.m.Y H:i'),
                'sent_at' => $newsletter->sent_at?->format('d.m.Y H:i'),
            ],
            'testRecipient' => app(ShopContact::class)->email(),
            'statuses' => NewsletterStatus::badges(),
        ]);
    }

    /**
     * Inhalt im GrapesJS-/MJML-Editor gestalten.
     */
    public function content(Newsletter $newsletter): Response
    {
        return Inertia::render('Admin/Newsletters/Content', [
            'newsletter' => [
                'id' => $newsletter->id,
                'name' => $newsletter->name,
                'status' => $newsletter->status,
                'design_json' => $newsletter->design_json,
                'editable' => $newsletter->isEditable(),
            ],
            'testRecipient' => app(ShopContact::class)->email(),
        ]);
    }

    /**
     * Speichert aus der Eingabemaske (Inertia-Formular, Redirect) und aus
     * dem Editor (Autosave per fetch, JSON).
     */
    public function update(UpdateNewsletterRequest $request, Newsletter $newsletter, HtmlSanitizer $sanitizer): JsonResponse|RedirectResponse
    {
        $data = $request->validated();

        if (array_key_exists('html', $data)) {
            $data['html'] = $data['html'] !== null ? $sanitizer->sanitize($data['html']) : null;
        }

        $newsletter->update($data);

        if (! $request->wantsJson()) {
            return back()->with('success', 'Newsletter gespeichert.');
        }

        return response()->json([
            'message' => 'Gespeichert.',
            'updated_at' => $newsletter->updated_at->format('H:i:s'),
        ]);
    }

    public function destroy(Newsletter $newsletter): RedirectResponse
    {
        if (! $newsletter->isEditable()) {
            return back()->with('error', 'Verschickte Newsletter bleiben erhalten.');
        }

        $newsletter->delete();

        return to_route('admin.newsletters.index')->with('success', 'Newsletter gelöscht.');
    }

    /**
     * Testversand an die Shop-Adresse (config/shop.php).
     */
    public function sendTest(Newsletter $newsletter): JsonResponse
    {
        $recipient = app(ShopContact::class)->email();

        if (! $recipient) {
            return response()->json(['message' => 'In config/shop.php ist keine Shop-E-Mail-Adresse hinterlegt.'], 422);
        }

        if (! $newsletter->hasContent()) {
            return response()->json(['message' => 'Der Newsletter ist noch leer. Bitte zuerst speichern.'], 422);
        }

        try {
            Mail::to($recipient)->send(new NewsletterMail($newsletter, isTest: true));
        } catch (\Throwable $e) {
            Log::channel('mail')->error('Newsletter test mail failed', [
                'newsletter_id' => $newsletter->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Die Testmail konnte nicht verschickt werden. Bitte das Mail-Log prüfen.'], 502);
        }

        return response()->json(['message' => "Testmail an {$recipient} verschickt."]);
    }

    /**
     * Bild-Upload aus dem Asset-Manager des Editors. Bilder liegen öffentlich,
     * weil Mail-Programme sie per absoluter URL laden.
     */
    public function uploadAsset(Request $request): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ]);

        /** @var array<int, UploadedFile> $files */
        $files = $request->file('files');

        $assets = collect($files)->map(function ($file): array {
            $path = $file->store('newsletters', 'public');

            return ['src' => Storage::disk('public')->url((string) $path)];
        })->values()->all();

        return response()->json(['data' => $assets]);
    }
}
