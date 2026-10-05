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
*/

return [
    ['slug' => 'ultraschallsysteme', 'name' => 'Ultraschallsysteme', 'description' => null],
    ['slug' => 'ultraschallsonden', 'name' => 'Ultraschallsonden', 'description' => null],
    ['slug' => 'verbrauchsartikel', 'name' => 'Verbrauchsartikel', 'description' => null],
    ['slug' => 'zubehoer', 'name' => 'Zubehör', 'description' => null],
];
