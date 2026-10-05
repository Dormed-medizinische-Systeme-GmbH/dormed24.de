<?php

namespace App\Support;

/**
 * Betriebsmodus für externe Dienste (PayPal, SMTP) und den Sandbox-Banner.
 *
 * Live nur, wenn APP_ENV=production UND APP_DEBUG=false — in jedem anderen
 * Fall (Staging, lokal, Produktion mit eingeschaltetem Debug) wird mit den
 * Sandbox-Zugangsdaten gearbeitet. Bewusst nicht per Setting oder Env-Flag
 * übersteuerbar.
 */
class PaymentMode
{
    public static function current(): string
    {
        return app()->environment('production') && ! config('app.debug') ? 'live' : 'sandbox';
    }

    public static function isLive(): bool
    {
        return self::current() === 'live';
    }
}
