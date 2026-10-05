<?php

/*
|--------------------------------------------------------------------------
| Produktkategorien
|--------------------------------------------------------------------------
| Feste Liste der Kategorien — nicht in der Datenbank. Die Reihenfolge hier
| ist die Reihenfolge in der Shop-Navigation und im Admin-Dropdown.
|
| slug        URL (/{slug}) und Wert in products.category_slug. Nach dem
|             Live-Gang nicht mehr ändern (Links, gespeicherte Produkte).
| name        Anzeigename.
| description Optionaler Text für die SEO-Beschreibung der Kategorieseite.
| has_specialties
|             Produkte dieser Kategorie bekommen Fachrichtungen
|             (config/specialties.php). Standard: false.
*/

return [
    ['slug' => 'ultraschallsysteme', 'name' => 'Ultraschallsysteme', 'description' => null, 'has_specialties' => true],
    ['slug' => 'ultraschallsonden', 'name' => 'Ultraschallsonden', 'description' => null, 'has_specialties' => true],
    ['slug' => 'verbrauchsartikel', 'name' => 'Verbrauchsartikel', 'description' => null],
    ['slug' => 'zubehoer', 'name' => 'Zubehör', 'description' => null],
];
