<?php

use App\Models\Customer;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'customers'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'customers',
        ],

        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],
    ],

    'providers' => [
        'customers' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', Customer::class),
        ],

        // Genau ein Admin, aus der Env statt aus der Datenbank (siehe 'admin' unten).
        'admins' => [
            'driver' => 'env-admin',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Shop-Admin
    |--------------------------------------------------------------------------
    | Der einzige Admin-Zugang. ADMIN_PASSWORD ist ein bcrypt-Hash, z. B. aus
    | `Hash::make()` per Tinker (Klartext wird nicht akzeptiert). Ohne
    | ADMIN_EMAIL oder ADMIN_PASSWORD ist kein Admin-Login möglich.
    */
    'admin' => [
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'passwords' => [
        'customers' => [
            'provider' => 'customers',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
