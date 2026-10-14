# Versandarten aus der Datenbank in `config/shipping.php`

## Ziel

Versandarten sind fest im Code definiert, nicht mehr in der Tabelle `shipping_methods` und nicht mehr im Admin pflegbar. Änderungen laufen über Commit und Deployment, wie bei Kategorien (`config/categories.php`) und Kontaktdaten (`config/shop.php`).

Gehärtet wird, indem alles Dynamische entfällt:

- keine Admin-CRUD-Routen, mit denen sich Preise ändern oder Versandarten löschen lassen
- kein `NULL`-Preis („auf Anfrage“) mehr, den `CartService` heute wegfiltern muss, damit er nicht als 0,00 € durchgeht
- keine Auto-Increment-IDs in Session und Request, stattdessen stabile String-Keys
- kein `sort_order`, die Reihenfolge ergibt sich aus der Config
- Preise als Integer-Cents statt `decimal`/Float
- keine DB-Abfrage pro Warenkorb-Aufruf (`CartServiceQueryCountTest` wird einfacher)

## Ist-Stand

| Stelle | Rolle heute |
| --- | --- |
| `app/Models/ShippingMethod.php`, `database/factories/ShippingMethodFactory.php` | Model (`name`, `description`, `price` nullable, `sort_order`) |
| Migrationen `2026_06_25_000002_create_shipping_methods_table`, `2026_06_28_134035_add_description_…` | Tabelle |
| `database/seeders/ShippingMethodSeeder.php` (+ Aufruf in `DatabaseSeeder`) | drei Versandarten: Selbstabholung 0 €, Standard DPD 9 €, Premium DPD 25 € |
| `app/Http/Controllers/Admin/ShippingMethodController.php`, `routes/admin.php:76-80` | Admin-CRUD unter `/admin/settings/shipping`, `/admin/settings` leitet dorthin |
| `resources/js/pages/Admin/Settings/Shipping.svelte`, `components/AppSidebar.svelte:50-56` | Admin-Maske und Navigationspunkt „Einstellungen“ |
| `app/Support/Cart/CartService.php` | lädt Models (`whereNotNull('price')`), validiert die Session-Auswahl, Default = erste |
| `app/Http/Requests/Cart/UpdateCartShippingMethodRequest.php` | `Rule::in(ShippingMethod::pluck('id'))` |
| `app/Support/Cart/ShippingOption.php`, `Cart.php` | DTO fürs Frontend (`id` als String) |
| Bestellung (`OrderManager::createFromCart`) | speichert nur `shipping_amount`, **nicht** welche Versandart gewählt wurde |

Das Frontend (`types/cart.ts`, `Checkout/Index.svelte`) kennt nur `id` als String und ist von der Umstellung kaum betroffen.

## Zielbild

### `config/shipping.php`

```php
return [
    ['key' => 'selbstabholung', 'name' => 'Selbstabholung', 'description' => 'Abholung nach Terminvereinbarung.', 'price_cents' => 0],
    ['key' => 'standard', 'name' => 'Standardversand (DPD)', 'description' => 'Lieferung innerhalb Deutschlands.', 'price_cents' => 900],
    ['key' => 'premium', 'name' => 'Premium-Versand (DPD)', 'description' => 'Versicherter Versand mit Sendungsverfolgung und Haftungsschutz.', 'price_cents' => 2500],
];
```

Mit Kopfkommentar im Stil von `config/categories.php`: Reihenfolge = Anzeige, die erste Versandart ist der Default, `key` darf nach dem Live-Gang nicht mehr geändert werden, die Preise sind brutto.

### `App\Support\Cart\ShippingMethods` (analog `App\Support\Catalog\Categories`)

- `all(): list<ShippingMethod>`, `find(?string $key): ?ShippingMethod`, `keys(): list<string>`, `default(): ShippingMethod`
- `ShippingMethod` als `final readonly` Value Object (`key`, `name`, `description`, `priceCents`). Der Name wird erst frei, wenn das Eloquent-Model weg ist; alternativ `ShippingRate` verwenden.
- Strenge Prüfung beim Lesen: `key` nicht leer und eindeutig, `price_cents` ist ein `int >= 0`, mindestens ein Eintrag. Ist die Config ungültig, wird eine Exception geworfen, damit eine kaputte Config nicht still als 0,00 € durchgeht.

## Schritte

1. **Config und Support-Klasse**: `config/shipping.php`, `ShippingMethods`, `ShippingMethod`-VO. Unit-Tests für `all/find/keys/default` und die Validierung: doppelter Key, negativer Preis, Float-Preis und leere Liste werfen eine Exception.
2. **CartService umstellen**: `shippingMethodModels()` und den Cache entfernen, `ShippingMethods::all()` verwenden. In `state()` und `clear()` ist der Default `ShippingMethods::default()->key`. Unbekannte Werte aus der Session, darunter alte numerische IDs wie `"2"`, fallen automatisch auf den Default zurück. Es braucht keine Session-Migration, der Kunde sieht nach dem Deploy aber einmal wieder die Standardauswahl.
3. **Request-Validierung**: `UpdateCartShippingMethodRequest` prüft gegen `Rule::in(ShippingMethods::keys())`.
4. **Admin entfernen**: `ShippingMethodController`, die vier Routen, `Admin/Settings/Shipping.svelte` und den Sidebar-Eintrag „Einstellungen“ löschen (es ist der einzige Eintrag dort). Für `/admin/settings` gibt es zwei Möglichkeiten: Route löschen (404) oder auf `/admin` umleiten. Empfehlung: löschen. `AdminAccessTest` anpassen.
5. **DB entfernen**: neue Migration `drop_shipping_methods_table`, deren `down()` die Tabelle mit allen Spalten wieder anlegt. Model, Factory und Seeder samt Aufruf in `DatabaseSeeder` löschen. Bestehende Bestellungen sind nicht betroffen, weil es keinen FK gibt und `orders.shipping_amount` erhalten bleibt.
6. **Optional, als Härtung empfohlen: Versandart an der Bestellung festhalten**. Neue Spalte `orders.shipping_method` (string, nullable für Altbestellungen), im `OrderManager` den Key als Snapshot setzen und in Admin-Bestellansicht und Mail den Namen anzeigen. Heute lässt sich bei einer Bestellung nicht nachvollziehen, ob abgeholt oder versendet wird.
7. **Tests umbauen**: Die acht Testdateien mit `ShippingMethod::factory()` (`CartFlowTest`, `CheckoutFlowTest`, `CheckoutVerificationTest`, `InvoicePurchaseLifecycleTest`, `CartServiceQueryCountTest`, `CartShapeTest`, `CartValueObjectsTest`, `ShippingMethodControllerTest`) bekommen ihre Versandarten über `config(['shipping' => [...]])`. `ShippingMethodControllerTest` entfällt mit dem Controller; der Wegfall der Routen wird mit einem 404-Test abgesichert (Löschen der Testdatei mit Freigabe). Die Vitest-Fixtures (`Checkout/Index.test.ts`, `Confirm.test.ts`) bekommen String-Keys statt `"1"`.
8. **Doku nachziehen**:
   - neue ADR `0006-versandarten-in-der-config.md`, die ADR 0004 in diesem Punkt ablöst (0004 nennt Versandarten ausdrücklich als Geschäftsdaten im Admin). In 0004 einen Verweis ergänzen.
   - `database/SCHEMA.md`: `shipping_methods` streichen, ggf. `orders.shipping_method` ergänzen
   - `AGENTS.md`, Abschnitt Warenkorb: Versandarten kommen aus `config/shipping.php`
   - `README.md` (Zeile zu `CartService`), `GLOSSARY.md` (Begriff „Versandart“), `.todo/architektur-plan.md:249` („Versandarten bleiben im Admin“), `.todo/sandbox-live-schema-trennung.md` (Versandarten werden nicht mehr kopiert)
9. **Prüfen**: lokal Pint, Prettier, ESLint, `npm run types:check` und Vitest; PHP-Tests und PHPStan in der CI über einen PR gegen `main`.

## Offene Fragen

1. **Preise stimmen nicht mit der Infoseite überein.** `Informationen/VersandUndZahlung.svelte` zeigt DPD-Staffelpreise nach Warenwert (bis 149,99 € netto 9,52 € netto bzw. 11,33 € brutto, ab 150 € 16,07 € netto …). Der Seeder hat pauschal 9,00 €. Welche Preise gelten? Falls die Staffel gilt, braucht die Config Preisstufen nach Warenwert. Das wäre zwar wieder etwas Logik, aber statisch und testbar. Die Infoseite sollte dann ihre Werte aus derselben Config beziehen statt sie doppelt hart zu codieren.
2. **Keys**: `selbstabholung` / `standard` / `premium` oder sprechender, etwa `dpd-standard`?
3. **Schritt 6** (Versandart an der Bestellung) in diesem PR oder separat?
4. **`/admin/settings`**: 404 oder Weiterleitung?
5. Sollen bestimmte Versandarten nur für bestimmte Fälle gelten (z. B. Selbstabholung nicht bei Sonden)? Vorschlag: nein. Wenn doch, dann als statische Regel in der Config.
