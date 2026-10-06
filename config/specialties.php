<?php

/*
|--------------------------------------------------------------------------
| Fachrichtungen
|--------------------------------------------------------------------------
| Feste Liste — nicht in der Datenbank. Nur für Produkte aus Kategorien mit
| 'has_specialties' => true (config/categories.php). Ein Produkt kann
| mehrere Fachrichtungen haben; gespeichert werden die Slugs direkt am
| Produkt (products.specialties). Die Reihenfolge hier ist die Reihenfolge
| im Admin, im Shop-Filter und auf der Produktseite.
|
| slug  Wert in products.specialties und im Filter (?fachrichtung[]=…).
|       Nach dem Live-Gang nicht mehr ändern (gespeicherte Produkte, Links).
| name  Anzeigename.
*/

return [
    ['slug' => 'allgemeinmedizin-innere-medizin', 'name' => 'Allgemeinmedizin / Innere Medizin'],
    ['slug' => 'gynaekologie-geburtshilfe', 'name' => 'Gynäkologie & Geburtshilfe'],
    ['slug' => 'kardiologie', 'name' => 'Kardiologie'],
    ['slug' => 'urologie', 'name' => 'Urologie'],
    ['slug' => 'angiologie', 'name' => 'Angiologie'],
    ['slug' => 'orthopaedie', 'name' => 'Orthopädie'],
    ['slug' => 'anaesthesie-notfallmedizin', 'name' => 'Anästhesie / Notfallmedizin'],
    ['slug' => 'paediatrie', 'name' => 'Pädiatrie'],
    ['slug' => 'radiologie', 'name' => 'Radiologie'],
    ['slug' => 'veterinaermedizin', 'name' => 'Veterinärmedizin'],
];
