<?php

namespace App\Support;

use App\Models\Setting;

/**
 * SMTP aus der Env: Die Zugangsdaten des aktiven Modus (sandbox/live, siehe
 * PaymentMode) aus config('mail.modes') werden in den smtp-Mailer übernommen.
 * Ohne Host für den aktiven Modus bleibt MAIL_MAILER (z. B. "log") unverändert.
 * Absendername ist der Shop-Name aus den Einstellungen.
 */
final class ModeMailConfiguration
{
    public static function apply(): void
    {
        /** @var array{host?: string|null, port?: int|string|null, username?: string|null, password?: string|null, from_address?: string|null} $smtp */
        $smtp = (array) config('mail.modes.'.PaymentMode::current(), []);

        if (filled($smtp['host'] ?? null)) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $smtp['host'],
                'mail.mailers.smtp.port' => (int) ($smtp['port'] ?? 587),
                'mail.mailers.smtp.username' => $smtp['username'] ?? null,
                'mail.mailers.smtp.password' => $smtp['password'] ?? null,
            ]);

            $fromAddress = filled($smtp['from_address'] ?? null) ? $smtp['from_address'] : ($smtp['username'] ?? null);

            if (filled($fromAddress) && filter_var($fromAddress, FILTER_VALIDATE_EMAIL) !== false) {
                config(['mail.from.address' => $fromAddress]);
            }
        }

        try {
            $shopName = Setting::get('shop.name');

            if ($shopName) {
                config(['mail.from.name' => $shopName]);
            }
        } catch (\Throwable) {
            // DB not yet available (e.g. during migrations)
        }
    }
}
