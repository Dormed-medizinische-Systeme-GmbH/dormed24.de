<?php

namespace App\Services;

use App\Support\PaymentMode;
use Srmklive\PayPal\Services\PayPal;

/**
 * Erzeugt den PayPal-SDK-Client mit der Konfiguration des aktuellen
 * Betriebsmodus. Der Client ist noch nicht authentifiziert, das Erzeugen
 * selbst macht keinen Netzwerkaufruf. Tests können die Factory im
 * Container ersetzen, um einen präparierten Client zu liefern.
 */
class PayPalClientFactory
{
    public function make(): PayPal
    {
        return $this->newClient($this->config());
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function newClient(array $config): PayPal
    {
        return new PayPal($config);
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return [
            'mode' => PaymentMode::current(),
            'sandbox' => [
                'client_id' => (string) config('paypal.sandbox.client_id', ''),
                'client_secret' => (string) config('paypal.sandbox.client_secret', ''),
                'app_id' => (string) config('paypal.sandbox.app_id', 'APP-80W284485P519543T'),
                'merchant_id' => (string) config('paypal.sandbox.merchant_id', ''),
            ],
            'live' => [
                'client_id' => (string) config('paypal.live.client_id', ''),
                'client_secret' => (string) config('paypal.live.client_secret', ''),
                'app_id' => (string) config('paypal.live.app_id', ''),
                'merchant_id' => (string) config('paypal.live.merchant_id', ''),
            ],
            'payment_action' => config('paypal.payment_action', 'Sale'),
            'currency' => config('paypal.currency', 'EUR'),
            'notify_url' => config('paypal.notify_url', ''),
            'locale' => config('paypal.locale', 'de_DE'),
            'validate_ssl' => config('paypal.validate_ssl', true),
            'timeout' => config('paypal.timeout', 30),
            'connect_timeout' => config('paypal.connect_timeout', 10),
            'max_retries' => config('paypal.max_retries', 2),
        ];
    }
}
