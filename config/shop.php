<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ProductSeeder-Freigabe in Produktion
    |--------------------------------------------------------------------------
    | Der ProductSeeder LÖSCHT den kompletten Katalog inkl. Bilder und
    | importiert die Shopware-CSV neu. In Produktion läuft er nur, wenn dies
    | bewusst über SEED_PRODUCTS_FORCE=true (Erstimport) freigegeben wird.
    | Default false = fail-safe: im Zweifel nichts löschen.
    */
    'seed_products_force' => env('SEED_PRODUCTS_FORCE', false),

    /*
    |--------------------------------------------------------------------------
    | Kontakt und Bankverbindung
    |--------------------------------------------------------------------------
    | Bewusst hier im Code und nicht im Admin oder in der Env
    | (.docs/adr/0004-keine-konfiguration-im-admin.md). Der Shopname ist
    | APP_NAME. Telefon und Fax bestehen aus Vorwahl und Rufnummer, daraus
    | entstehen Anzeige ("02301 / 188-600") und tel:-Link (App\Support\PhoneNumber).
    */
    'contact' => [
        'email' => 'mail@dormed.de',
        'phone' => ['area_code' => '02301', 'number' => '188600'],
        'fax' => ['area_code' => '02301', 'number' => '188620'],
    ],

    'bank' => [
        'account_holder' => 'Dormed medizinische Systeme GmbH',
        'iban' => '',
        'bic' => '',
        'name' => 'Sparkasse UnnaKamen',
    ],

    'cart' => [
        'session_key' => 'cart',
        'vat_rate' => 19,
    ],
];
