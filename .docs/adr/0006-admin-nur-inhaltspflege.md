# Admin nur für Inhaltspflege, Versandarten im Code

Status: accepted (Oktober 2026)

Der Admin pflegt nur Inhalte (Produkte, Hersteller, Magazin, Newsletter, Social-Media) und bearbeitet Bestellungen. Alles Administrative steht im Code und ändert sich über Commit und Deployment. Das löst ADR 0004 in einem Punkt ab: Versandarten galten dort als Geschäftsdaten im Admin, jetzt stehen sie in `config/shipping.php` und die Tabelle `shipping_methods` ist entfernt.

Anlass ist die Härtung des Checkouts. Über die Admin-Maske ließen sich Preise ändern und Versandarten löschen, ein `NULL`-Preis („auf Anfrage“) musste im Warenkorb eigens herausgefiltert werden, damit er nicht als 0,00 € durchging, und Session und Request trugen Auto-Increment-IDs. Jetzt gilt:

- Preise sind ganzzahlige Brutto-Cents, „auf Anfrage“ gibt es nicht.
- `App\Support\Cart\ShippingMethods` prüft die Config bei jedem Zugriff und wirft bei einem Fehler eine Exception. Dieselben Regeln prüft `tests/Feature/Config/ShippingConfigTest` an der echten Datei, und weil der Deploy an den Tests hängt, geht eine fehlerhafte Config nicht live.
- Die Bestellung speichert Key, Name und Betrag der gewählten Versandart als Snapshot (`orders.shipping_method_key`, `shipping_method_name`, `shipping_amount`). Keys und Namen dürfen sich in der Config ändern, Versandarten dürfen hinzukommen oder wegfallen, ohne dass alte Bestellungen sich verändern. Warenkörbe mit einem unbekannten Key springen auf die erste Versandart.
