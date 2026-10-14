<?php

/*
|--------------------------------------------------------------------------
| Versandarten
|--------------------------------------------------------------------------
| Feste Liste der Versandarten — nicht in der Datenbank und nicht im Admin
| (.docs/adr/0006-admin-nur-inhaltspflege.md). Die Reihenfolge hier ist die
| Reihenfolge im Checkout, die erste Versandart ist der Standard.
|
| key          Wert in Warenkorb-Session und orders.shipping_method_key.
|              Kleinbuchstaben, Ziffern und Bindestriche. Darf geändert
|              werden: Warenkörbe mit dem alten Key springen auf den
|              Standard, Bestellungen behalten ihren Snapshot.
| name         Anzeigename, wird als Snapshot an der Bestellung gespeichert.
| description  Optionaler Kurztext im Checkout.
| price_cents  Bruttopreis in Cent als Ganzzahl (900 = 9,00 €).
|
| Jeder Eintrag wird in der CI geprüft (tests/Feature/Config/ShippingConfigTest),
| ein fehlerhafter Eintrag blockiert das Deployment.
*/

return [
    [
        'key' => 'selbstabholung',
        'name' => 'Selbstabholung',
        'description' => 'Abholung nach Terminvereinbarung.',
        'price_cents' => 0,
    ],
    [
        'key' => 'standard',
        'name' => 'Standardversand (DPD)',
        'description' => 'Lieferung innerhalb Deutschlands.',
        'price_cents' => 900,
    ],
    [
        'key' => 'premium',
        'name' => 'Premium-Versand (DPD)',
        'description' => 'Versicherter Versand mit Sendungsverfolgung und Haftungsschutz.',
        'price_cents' => 2500,
    ],
];
