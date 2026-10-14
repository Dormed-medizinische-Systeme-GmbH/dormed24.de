# Versandarten aus der Datenbank in `config/shipping.php`

## Stand

Umgesetzt (Schritte 1–8). Eine fehlerhafte `config/shipping.php` darf nie live gehen. Deshalb prüft `tests/Feature/Config/ShippingConfigTest` die echte Datei mit denselben Regeln wie `ShippingMethods::errors()`. Der Deploy auf `main` hängt an den Tests (`deploy.yml`: `needs: [tests, lint]`). Dieselbe Klasse testet jeden Fehlertyp einzeln, damit die Prüfung nicht ins Leere läuft. Die Exception zur Laufzeit ist nur die zweite Absicherung. Offen ist nur noch die Infoseite (siehe unten).

## Ziel

Versandarten sind fest im Code definiert, nicht mehr in der Tabelle `shipping_methods` und nicht mehr im Admin pflegbar. Änderungen laufen über Commit und Deployment, wie bei Kategorien (`config/categories.php`) und Kontaktdaten (`config/shop.php`).

Grundsatz (entschieden): **Der Admin pflegt nur Inhalte** (Produkte, Hersteller, Magazin, Newsletter, Social-Media) und bearbeitet Bestellungen. Alles Administrative steht im Code. Mit diesem Umbau fällt der letzte Konfigurationspunkt im Admin („Einstellungen → Versandarten“) weg.

Gehärtet wird, indem alles Dynamische entfällt:

- keine Admin-CRUD-Routen, mit denen sich Preise ändern oder Versandarten löschen lassen
- kein `NULL`-Preis („auf Anfrage“) mehr, den `CartService` heute wegfiltern muss, damit er nicht als 0,00 € durchgeht
- keine Auto-Increment-IDs in Session und Request, stattdessen String-Keys aus der Config
- kein `sort_order`, die Reihenfolge ergibt sich aus der Config
- Preise als Integer-Cents statt `decimal`/Float
- keine DB-Abfrage pro Warenkorb-Aufruf
- die Bestellung hält einen vollständigen Snapshot der Versandart, unabhängig davon, was später in der Config steht

## Entscheidungen

| Frage | Entscheidung |
| --- | --- |
| Preise | Übernahme des aktuellen Stands: Selbstabholung 0,00 €, Standardversand (DPD) 9,00 €, Premium-Versand (DPD) 25,00 € (brutto, pauschal) |
| Keys | dürfen sich später ändern; Versandarten kommen hinzu. Deshalb hängt nichts Dauerhaftes am Key: Die Bestellung speichert Key **und** Name **und** Betrag als Snapshot. |
| Versandart an der Bestellung | ja, als Snapshot in diesem Umbau |
| `/admin/settings` | Route und Navigationsgruppe „Einstellungen“ entfallen (404) |

## Ist-Stand

| Stelle | Rolle heute |
| --- | --- |
| `app/Models/ShippingMethod.php`, `database/factories/ShippingMethodFactory.php` | Model (`name`, `description`, `price` nullable, `sort_order`) |
| Migrationen `2026_06_25_000002_create_shipping_methods_table`, `2026_06_28_134035_add_description_…` | Tabelle |
| `database/seeders/ShippingMethodSeeder.php` (+ Aufruf in `DatabaseSeeder`) | die drei Versandarten |
| `app/Http/Controllers/Admin/ShippingMethodController.php`, `routes/admin.php:75-80` | Admin-CRUD unter `/admin/settings/shipping`, `/admin/settings` leitet dorthin |
| `resources/js/pages/Admin/Settings/Shipping.svelte`, `components/AppSidebar.svelte:50-56, 78` | Admin-Maske, Navigationsgruppe „Einstellungen“ |
| `app/Support/Cart/CartService.php` | lädt Models (`whereNotNull('price')`), validiert die Session-Auswahl, Default = erste |
| `app/Http/Requests/Cart/UpdateCartShippingMethodRequest.php` | `Rule::in(ShippingMethod::pluck('id'))` |
| `app/Support/Cart/ShippingOption.php`, `Cart.php` | DTO fürs Frontend (`id` als String) |
| `OrderManager::createFromCart` | speichert nur `shipping_amount`, **nicht** welche Versandart |

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

Mit Kopfkommentar im Stil von `config/categories.php`:

- Reihenfolge = Anzeige, die erste Versandart ist der Default
- Preise sind Brutto-Cents
- Neue Versandart: Eintrag ergänzen.
- Key ändern oder Versandart entfernen: erlaubt. Warenkörbe mit dem alten Key springen auf den Default, bestehende Bestellungen behalten ihren Snapshot.

### `App\Support\Cart\ShippingMethods` (analog `App\Support\Catalog\Categories`)

- `all(): list<ShippingMethod>`, `find(?string $key): ?ShippingMethod`, `keys(): list<string>`, `default(): ShippingMethod`
- `ShippingMethod` als `final readonly` Value Object (`key`, `name`, `description`, `priceCents`). Der Name wird erst frei, wenn das Eloquent-Model gelöscht ist.
- Strenge Prüfung beim Lesen, damit eine kaputte Config nicht still als 0,00 € durchgeht. Bei Fehlern wird eine Exception geworfen. Geprüft wird:
  - `key` ist nicht leer, eindeutig und folgt dem Muster `[a-z0-9-]+`
  - `name` ist nicht leer
  - `price_cents` ist ein `int >= 0`
  - die Liste hat mindestens einen Eintrag

### Snapshot an der Bestellung

Neue Spalten in `orders` (nullable, weil Altbestellungen keine Versandart haben):

| Spalte | Typ | Inhalt |
| --- | --- | --- |
| `shipping_method_key` | varchar | Key zum Zeitpunkt der Bestellung |
| `shipping_method_name` | varchar | Anzeigename zum Zeitpunkt der Bestellung |

Der Betrag steht bereits in `shipping_amount`. Für die Anzeige liest jede Stelle nur den Snapshot, nie die Config. Das betrifft:

- Admin-Bestellansicht
- Kundenbereich „Bestellungen“
- Bestätigungsseite
- `NewOrderMail`

## Schritte

1. **Config und Support-Klasse**: `config/shipping.php`, `ShippingMethods`, `ShippingMethod`-VO. Unit-Tests für `all/find/keys/default` und die Validierung: doppelter Key, ungültiger Key, leerer Name, negativer Preis, Float- oder String-Preis und leere Liste werfen jeweils eine Exception.
2. **Cart**:
   - `CartService`: `shippingMethodModels()` und den Cache entfernen, `ShippingMethods::all()` verwenden.
   - `state()` und `clear()`: Default ist `ShippingMethods::default()->key`. Unbekannte Werte aus der Session, darunter alte numerische IDs wie `"2"` und umbenannte Keys, fallen auf den Default zurück. Es braucht keine Session-Migration.
   - `Cart` bekommt Zugriff auf die gewählte Versandart (Key und Name) für den Snapshot.
3. **Request-Validierung**: `UpdateCartShippingMethodRequest` prüft gegen `Rule::in(ShippingMethods::keys())`.
4. **Snapshot**:
   - Migration `add_shipping_method_snapshot_to_orders_table`
   - `Order` erweitern (Fillable, PHPDoc)
   - `OrderManager::createFromCart` setzt Key und Name
   - Anzeige an den vier Stellen oben, mit Fallback „—“ für Altbestellungen
   - Frontend-Typ `types/order.ts` erweitern
5. **Admin entfernen**:
   - `ShippingMethodController`
   - die fünf `settings`-Routen inklusive `/admin/settings`
   - `Admin/Settings/Shipping.svelte`
   - in `AppSidebar.svelte` die Gruppe „Einstellungen“ samt `settingsItems`
   - `AdminAccessTest` anpassen
   - ein Test, dass `/admin/settings` und `/admin/settings/shipping` 404 liefern
6. **DB entfernen**: Migration `drop_shipping_methods_table`, deren `down()` die Tabelle mit allen Spalten wieder anlegt. Model, Factory und Seeder samt Aufruf in `DatabaseSeeder` löschen. Bestehende Bestellungen sind nicht betroffen, weil es keinen FK gibt.
7. **Tests umbauen**:
   - Die PHP-Tests mit `ShippingMethod::factory()` setzen ihre Versandarten über `config(['shipping' => [...]])`: `CartFlowTest`, `CheckoutFlowTest`, `CheckoutVerificationTest`, `InvoicePurchaseLifecycleTest`, `CartServiceQueryCountTest`, `CartShapeTest`, `CartValueObjectsTest`.
   - Neu ist ein Test, dass der Snapshot in der Bestellung landet und eine spätere Config-Änderung ihn nicht verändert.
   - `ShippingMethodControllerTest` entfällt mit dem Controller (Löschen mit Freigabe).
   - Die Vitest-Fixtures (`Checkout/Index.test.ts`, `Confirm.test.ts`, Order-Layout-Tests) bekommen String-Keys und die neuen Snapshot-Felder.
8. **Doku nachziehen**:
   - neue ADR `0006-admin-nur-inhaltspflege.md`: Versandarten in der Config und der Grundsatz „Admin = Inhalte, Administratives = Code“. Sie löst ADR 0004 in dem Punkt ab, dass Versandarten Geschäftsdaten im Admin seien; in 0004 einen Verweis ergänzen.
   - `database/SCHEMA.md`: `shipping_methods` streichen, `orders.shipping_method_key`/`_name` ergänzen
   - `AGENTS.md`, Abschnitt Warenkorb: Versandarten kommen aus `config/shipping.php`, die Bestellung hält einen Snapshot
   - `README.md` (Zeile zu `CartService`, Kern-Flow Bestellung)
   - `GLOSSARY.md` (Begriff „Versandart“)
   - `.todo/architektur-plan.md:249` („Versandarten bleiben im Admin“)
   - `.todo/sandbox-live-schema-trennung.md` (Versandarten werden nicht mehr kopiert)
9. **Prüfen**: lokal Pint, Prettier, ESLint, `npm run types:check` und Vitest; PHP-Tests und PHPStan in der CI über einen PR gegen `main`.

## Offen

- **Infoseite `Informationen/VersandUndZahlung.svelte`** zeigt eine DPD-Staffel nach Warenwert (z. B. 11,33 € brutto bis 178,49 €, darüber mehr). Das widerspricht den Pauschalpreisen 9,00 € und 25,00 €. Vorschlag: Die Seite rendert die Versandarten aus derselben Config (über ein Inertia-Prop), statt Preise doppelt hart zu codieren. Ob die Staffel inhaltlich noch gilt, muss fachlich geklärt werden. Mit diesem Umbau oder separat?
