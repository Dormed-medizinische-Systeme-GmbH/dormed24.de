<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    private const ALLOWED_KEYS = [
        'shop.name',
        'shop.email',
        'shop.phone',
        'shop.fax',
        'shop.bank_account_holder',
        'shop.bank_iban',
        'shop.bank_bic',
        'shop.bank_name',
    ];

    public function showGeneral(): Response
    {
        $settings = $this->loadSettings();

        return Inertia::render('Admin/Settings/General', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        $unknownKeys = array_diff(array_keys($data['settings']), self::ALLOWED_KEYS);

        if ($unknownKeys !== []) {
            throw ValidationException::withMessages([
                'settings' => 'Unbekannte Einstellungs-Keys: '.implode(', ', $unknownKeys),
            ]);
        }

        foreach ($data['settings'] as $key => $value) {
            if ($value !== null && (! is_string($value) || strlen($value) > 1000)) {
                throw ValidationException::withMessages([
                    'settings' => "Ungültiger Wert für \"{$key}\".",
                ]);
            }

            $this->assertValidFormat($key, $value);
        }

        foreach ($data['settings'] as $key => $value) {
            Setting::set($key, $value ?: null);
        }

        return back()->with('success', 'Einstellungen gespeichert.');
    }

    /**
     * Format-Validierung für Keys, bei denen ein Tippfehler sonst erst
     * auffällt, wenn Mails still im Log landen (shop.email ist der Empfänger
     * aller Bestellbenachrichtigungen). Leere Werte sind zulässig (= löschen).
     */
    private function assertValidFormat(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $error = match ($key) {
            'shop.email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false
                ? 'Bitte eine gültige E-Mail-Adresse angeben.'
                : null,
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages([
                "settings.{$key}" => $error,
            ]);
        }
    }

    private function loadSettings(): array
    {
        $raw = Setting::all(['key', 'value'])->pluck('value', 'key');

        $settings = [
            'shop.name' => $raw->get('shop.name', ''),
            'shop.email' => $raw->get('shop.email', ''),
            'shop.phone' => $raw->get('shop.phone', ''),
            'shop.fax' => $raw->get('shop.fax', ''),
            'shop.bank_account_holder' => $raw->get('shop.bank_account_holder', ''),
            'shop.bank_iban' => $raw->get('shop.bank_iban', ''),
            'shop.bank_bic' => $raw->get('shop.bank_bic', ''),
            'shop.bank_name' => $raw->get('shop.bank_name', ''),
        ];

        return $settings;
    }
}
