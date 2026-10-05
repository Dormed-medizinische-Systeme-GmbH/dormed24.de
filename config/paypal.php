<?php

/**
 * PayPal client configuration (srmklive/paypal).
 *
 * Zugangsdaten kommen ausschließlich aus der Env (nicht aus der Datenbank).
 * Welcher Satz genutzt wird (sandbox/live), entscheidet App\Support\PaymentMode:
 * live nur bei APP_ENV=production und APP_DEBUG=false.
 * Die Webhook-ID ist pro PayPal-App verschieden, daher je Modus.
 */

return [
    'sandbox' => [
        'client_id' => env('PAYPAL_SANDBOX_CLIENT_ID', ''),
        'client_secret' => env('PAYPAL_SANDBOX_CLIENT_SECRET', ''),
        'app_id' => 'APP-80W284485P519543T', // Sandbox app_id is always this fixed value.
        'merchant_id' => env('PAYPAL_SANDBOX_MERCHANT_ID', ''),
        'webhook_id' => env('PAYPAL_SANDBOX_WEBHOOK_ID', ''),
    ],
    'live' => [
        'client_id' => env('PAYPAL_LIVE_CLIENT_ID', ''),
        'client_secret' => env('PAYPAL_LIVE_CLIENT_SECRET', ''),
        // Live app_id: developer.paypal.com → My Apps & Credentials → App ID ("APP-…").
        'app_id' => env('PAYPAL_LIVE_APP_ID', ''),
        'merchant_id' => env('PAYPAL_LIVE_MERCHANT_ID', ''),
        'webhook_id' => env('PAYPAL_LIVE_WEBHOOK_ID', ''),
    ],

    'payment_action' => 'Sale', // Can only be 'Sale', 'Authorization' or 'Order'
    'currency' => 'EUR',
    'notify_url' => '',
    'locale' => 'de_DE',
    'validate_ssl' => true,
    'timeout' => 30, // Total request timeout in seconds.
    'connect_timeout' => 10, // Connection timeout in seconds.
    'max_retries' => 2, // Retries on 5xx / connection errors (0 to disable). Uses exponential backoff.
];
