# Plan: Sandbox- und Live-Bestellungen per Postgres-Schema trennen

> Status: **Entwurf, nicht umgesetzt.** Grundlage ist der Stand von `main` nach PR #9 (Oktober 2026).

## Ziel

- Eine Bestellung im Sandbox-Modus landet im Schema `sandbox`, eine Bestellung im Live-Modus im Schema `live`.
- Der Admin-Bereich zeigt immer nur die Daten des aktiven Modus. Das gilt für Dashboard, Bestellungen und Bestell-Historie beim Kunden.
- Man kann jederzeit in die Sandbox schalten und herumprobieren. Nach dem Zurückschalten auf Live ist davon nichts zu sehen.
- Die Trennung passiert auf Datenbankebene. Ein vergessenes `where('is_test', …)` kann also nichts mehr vermischen.

## Ausgangslage im Code

| Thema | Heute |
|---|---|
| Modus | `App\Support\PaymentMode`: `live` nur bei `APP_ENV=production` **und** `APP_DEBUG=false`, sonst `sandbox`. Absichtlich weder per Setting noch per Env-Flag umschaltbar. |
| Kennzeichnung | `orders.is_test` wird beim Anlegen gesetzt (`OrderManager`, Zeile 36), aber **nirgends gefiltert**. Das Dashboard zählt Sandbox-Bestellungen mit (`DashboardControllerTest` dokumentiert das bewusst). |
| PayPal | Zugangsdaten und Webhook-ID kommen per `PaymentMode::current()` aus `config/paypal.php`. Es gibt **eine** Webhook-Route (`/paypal/webhook`), die mit der Webhook-ID des *aktuellen* Modus prüft. |
| Bestell-Tabellen | `orders`, `order_items` (FK auf `orders`; `product_id` ohne FK), `payments` (FK auf `orders`). `orders.customer_id` ist ein FK auf `customers` mit `nullOnDelete`. |
| Mails | werden synchron versendet (`Mail::send`, keine Queue). |
| Session, Cache, Jobs | liegen in der Datenbank (`SESSION_DRIVER`, `CACHE_STORE` und `QUEUE_CONNECTION` stehen auf `database`). |
| Tests/CI | SQLite in-memory, kein Postgres-Service in `tests.yml`. |

## Grundsatzentscheidungen (vor der Umsetzung klären)

### E1: Wie wird der Modus umgeschaltet? (Pflicht)

„Jederzeit in die Sandbox stellen“ geht heute nur über `APP_DEBUG=true` auf Produktion. Das blendet Stacktraces für Besucher ein und kommt deshalb **nicht in Frage**.

- **Empfehlung:** ein eigenes Env-Flag `SHOP_MODE=live|sandbox`, getrennt von `APP_DEBUG`. Umschalten heißt dann: `.env` ändern und `php artisan optimize`. Das bleibt nachvollziehbar und kann nicht versehentlich im Admin passieren.
  - Live nur, wenn `APP_ENV=production` **und** `SHOP_MODE=live`. Lokal und auf Staging bleibt es immer Sandbox, das ist die heutige Schutzregel.
- **Alternative:** ein Schalter im Admin (Setting). Bequemer, aber der Modus wird dann zu Laufzeit-Zustand und braucht Absicherung, z. B. eine Bestätigung und einen Audit-Log-Eintrag.

Der Docblock von `PaymentMode` und `OperatingModeTest` müssen danach angepasst werden.

### E2: Was liegt pro Modus, was ist gemeinsam?

| Schema `public` (gemeinsam) | Schema `sandbox` / `live` (getrennt) |
|---|---|
| `customers`, `addresses`, `passkeys` | `orders` |
| `products`, `product_images`, `product_variants`, `manufacturers` | `order_items` |
| `shipping_methods`, `settings` | `payments` |
| `sessions`, `cache`, `jobs`, `migrations` | `ratings`, *sobald* sie bestellbasiert werden (heute deaktiviert) |

**Empfehlung: Kunden bleiben gemeinsam.** Ein echter Kunde, der sich registriert, während die Sandbox aktiv ist, darf beim Umschalten nicht verschwinden. Damit bleiben zwei Dinge sichtbar:

- Testkunden. Lösung: per Konvention mit eigener Mailadresse testen oder später über einen Befehl aufräumen.
- Kundendetails: Ein Kunde, der nur Sandbox-Bestellungen hat, erscheint im Live-Admin ohne Bestellungen. Das ist korrekt.

Soll wirklich *nichts* sichtbar bleiben, gehören auch `customers` und `addresses` ins Modus-Schema. Dann müsste man sich aber pro Modus getrennt registrieren, Login und Passkeys wären doppelt. Davon rate ich ab.

### E3: Gibt es Bestandsdaten in Postgres?

`.env.example` steht auf SQLite. **Vorab auf dem Server prüfen, welche Datenbank tatsächlich läuft.** Ist es noch nicht Postgres, ist der Umzug auf Postgres eine eigene Phase 0, mit Export, Import und Sequenz-Reset, und kommt vor allem anderen.

## Technischer Ansatz

### Mechanik: `search_path` pro Request

Postgres löst unqualifizierte Tabellennamen über den `search_path` auf. Die App bekommt deshalb pro Prozess bzw. Request:

```
search_path = <modus>, public      -- also "live, public" oder "sandbox, public"
```

- `orders`, `order_items` und `payments` gibt es **nur** in `live` und `sandbox`, alle anderen Tabellen **nur** in `public`. Jeder Name ist damit eindeutig.
- Alle bestehenden Queries, Joins, `whereHas`, Fremdschlüssel und Relationen funktionieren **unverändert**. Ein FK aus `live.orders` auf `public.customers` ist in Postgres innerhalb einer Datenbank erlaubt.
- Kein Model braucht eine eigene Connection. Das ist der große Vorteil gegenüber „zweite Connection für Bestell-Models“: Dort würden Cross-Connection-Abfragen wie `Customer::whereHas('orders')` oder die Dashboard-Aggregate stillschweigend falsch oder gar nicht funktionieren.

Umsetzung:

1. `config/database.php`, Connection `pgsql`: `search_path` nicht mehr fest `public`, sondern aus dem Modus. Die Config ist aber gecacht, deshalb besser:
2. Ein `App\Support\ShopSchema::apply()` im `AppServiceProvider::boot()` (neben `ModeMailConfiguration::apply()`) setzt `config(['database.connections.pgsql.search_path' => PaymentMode::current().', public'])` und ruft `DB::purge('pgsql')` auf, bevor die erste Query läuft.
3. Absicherung: Nach dem Verbinden prüft `SHOW search_path`, ob das erwartete Schema vorne steht. Andernfalls gibt es eine harte Exception statt stiller Schreibzugriffe ins falsche Schema.

### Langlaufende Prozesse

- **Queue-Worker** (`QUEUE_CONNECTION=database`): Ein Worker liest den Modus einmal beim Start. Nach dem Umschalten deshalb `php artisan queue:restart` ausführen, das gehört in die Umschalt-Checkliste.
  - Jobs, die Bestellungen anfassen, merken sich zusätzlich den Modus beim Dispatch (Job-Middleware `RunInShopMode`). Ein vor dem Umschalten eingestellter Job läuft so im richtigen Schema. Heute gibt es solche Jobs noch nicht, Mails gehen synchron. Die Regel gilt für künftige Jobs.
- **Scheduler/Artisan:** Befehle laufen im aktuellen Modus. Befehle, die explizit ein Schema brauchen, bekommen `--mode=sandbox|live`.

### PayPal: Webhooks und Rückkehr-URLs nach Modus trennen

Das ist die eigentliche Falle. Szenario: Eine Sandbox-Zahlung ist noch offen, dann wird auf Live geschaltet, danach kommt der Sandbox-Webhook an. Heute würde er mit der **Live**-Webhook-ID geprüft, abgelehnt und die Sandbox-Bestellung bliebe hängen. Mit Schemas würde er sogar im Live-Schema nach der Bestellung suchen.

- Zwei Routen: `POST /paypal/webhook/sandbox` und `POST /paypal/webhook/live`.
  - Jede prüft mit den Zugangsdaten und der Webhook-ID **ihres** Modus und schaltet für diesen Request den `search_path` auf ihr Schema, unabhängig vom aktiven Modus.
  - Die URLs werden in den beiden PayPal-Apps (Sandbox-Dashboard, Live-Dashboard) hinterlegt.
  - Die alte Route `/paypal/webhook` bleibt eine Übergangszeit als Alias auf Live und wird dann entfernt.
- `PayPalService` bekommt den Modus als Parameter (Standard: aktueller Modus), statt intern `PaymentMode::current()` aufzurufen.
- **Rückkehr nach der Zahlung** (`paypal.after-payment`, `checkout.success`): Der Modus der Bestellung wird beim Anlegen der PayPal-Order in die Session geschrieben (`checkout.mode`). Weicht er bei der Rückkehr vom aktiven Modus ab, gibt es keine Verbuchung im falschen Schema, sondern eine klare Fehlerseite: „Der Shop wurde während Ihrer Zahlung umgestellt“. Der Webhook der richtigen Route verbucht die Zahlung trotzdem.

### Bestellnummern

Jedes Schema hat eigene Sequenzen, Bestellung #5 gäbe es also zweimal. Empfehlung:

- Die Sandbox-Sequenz startet bei `900000000` (`ALTER SEQUENCE sandbox.orders_id_seq RESTART WITH 900000000`). Sandbox-Nummern sind damit in Mails, Logs und PayPal sofort erkennbar.
- Die Live-Sequenz übernimmt den bisherigen Stand (siehe Datenmigration).

### Migrationen

Laravel-Migrationen kennen nur einen `search_path`. Für die Modus-Tabellen deshalb:

- Ein Helper `ShopSchema::each(fn (string $schema) => …)`, der eine Schema-Änderung für `sandbox` **und** `live` ausführt, mit schemaqualifizierten Namen (`Schema::create('live.orders', …)`).
- Konvention, z. B. in `CLAUDE.md` festgehalten: Jede künftige Migration an `orders`, `order_items`, `payments` (später `ratings`) **muss** `ShopSchema::each` benutzen. Ein Test vergleicht die Spalten beider Schemas und schlägt fehl, wenn sie auseinanderlaufen.
- Die Tabelle `migrations` bleibt in `public`. `migrate` läuft beim Deploy weiter einmal (`migrate --force`).

### Datenmigration (einmalig)

Eine Migration `move_orders_into_mode_schemas`:

1. `CREATE SCHEMA IF NOT EXISTS live; CREATE SCHEMA IF NOT EXISTS sandbox;`
2. `orders`, `order_items` und `payments` in beiden Schemas anlegen, mit identischer Struktur und FKs auf `public.customers`.
3. Daten verschieben:
   - `is_test = false` → `live`
   - `is_test = true` → `sandbox`

   Die zugehörigen `order_items` und `payments` gehen jeweils mit. IDs bleiben erhalten.
4. Live-Sequenz auf `max(id) + 1` setzen, Sandbox-Sequenz wie oben.
5. Die alten Tabellen in `public` erst **umbenennen** (`orders_legacy` …) statt löschen. Gelöscht wird erst in einem späteren Release, wenn alles läuft.
6. Wie bei der Kategorien-Migration: zuerst prüfen, dann ändern. Bricht die Prüfung ab, zum Beispiel wegen verwaister `payments`, ist noch nichts verändert.
7. `is_test` danach entfernen (eigener Schritt). Das Schema *ist* die Kennzeichnung.

### Admin-Bereich

- Funktional gibt es nichts zu tun: Der Admin liest automatisch aus dem aktiven Schema.
- Ein gut sichtbares Badge „Sandbox-Daten“ im Admin-Header, analog zum bestehenden Sandbox-Banner im Shop (`sandbox`-Prop aus `HandleInertiaRequests`).
- Die Kundendetailseite zeigt nur Bestellungen des aktiven Modus. Das ist gewollt und gehört in die Doku.
- Optional: Ein Befehl `php artisan sandbox:reset` leert das Sandbox-Schema per `TRUNCATE sandbox.orders, sandbox.order_items, sandbox.payments RESTART IDENTITY` und setzt die Sequenz zurück. Er läuft nur, wenn der aktive Modus **nicht** Sandbox ist, oder mit `--force`.

### Tests und CI

- SQLite kennt weder Schemas noch `search_path`. Für diese Logik braucht die CI einen **Postgres-Service** in `.github/workflows/tests.yml`. Das ist eine Infrastruktur- bzw. Abhängigkeitsänderung und braucht Freigabe.
- Vorschlag: Die bestehende Suite läuft weiter auf SQLite, dort greift kein `ShopSchema`. Eine neue Testgruppe `#[Group('pgsql')]` läuft gegen Postgres:
  - Eine Sandbox-Bestellung landet in `sandbox.orders`, eine Live-Bestellung in `live.orders`.
  - Der Admin im Live-Modus sieht keine Sandbox-Bestellung und umgekehrt, für Bestellliste, Dashboard und Kunden-Historie.
  - Beide Webhook-Routen verbuchen ins richtige Schema, auch wenn der aktive Modus der andere ist.
  - Rückkehr nach einem Moduswechsel führt zur Fehlerseite, nicht zu einer Verbuchung im falschen Schema.
  - Die Datenmigration verteilt Bestandsdaten nach `is_test` und setzt die Sequenzen korrekt.
  - Die Strukturen von `sandbox` und `live` sind identisch (Schutz gegen vergessene `ShopSchema::each`).
  - Die Absicherung schlägt hart fehl, wenn der `search_path` nicht passt.
- `DashboardControllerTest` anpassen: Der `is_test`-Fall entfällt.

## Umsetzungsreihenfolge

| Phase | Inhalt | Ergebnis |
|---|---|---|
| 0 | E1–E3 entscheiden; ggf. Umzug auf Postgres | klare Grundlage |
| 1 | `SHOP_MODE`-Flag, `PaymentMode` anpassen, Admin-Badge | Umschalten ohne `APP_DEBUG` |
| 2 | PayPal: zwei Webhook-Routen, `PayPalService` mit Modus-Parameter, Session-Pin beim Checkout | Webhooks unabhängig vom aktiven Modus korrekt; **auch ohne Schemas schon ein Gewinn** |
| 3 | CI-Postgres-Service, `ShopSchema` (`apply`, `each`, Absicherung), Tests | Mechanik steht |
| 4 | Datenmigration in die Schemas, Sequenzen, Legacy-Tabellen umbenennen | Daten getrennt |
| 5 | `is_test` entfernen, Legacy-Tabellen löschen, `sandbox:reset` | Aufräumen |

Jede Phase ist ein eigener PR. Phase 2 kann unabhängig vom Rest zuerst kommen.

## Checkliste für den Moduswechsel (Betrieb)

1. In der `.env` `SHOP_MODE` umstellen.
2. `php artisan optimize` und `php artisan queue:restart` ausführen.
3. Im Admin prüfen, ob das Badge den erwarteten Modus zeigt.
4. Offene Zahlungen des alten Modus laufen über dessen Webhook-Route weiter. Hier ist nichts zu tun.

## Risiken und offene Punkte

- **Eine vergessene `ShopSchema::each`-Migration** lässt die Schemas auseinanderlaufen. Gegenmittel: Strukturvergleich-Test plus Konvention in `CLAUDE.md`.
- **Rohes SQL mit `public.` davor** umgeht die Trennung. Grep-Regel im Review.
- **Backups/Restore** müssen alle drei Schemas umfassen. `pg_dump` macht das standardmäßig, ein Restore einzelner Schemas aber nicht.
- **Testkunden** bleiben im gemeinsamen Schema sichtbar (siehe E2).
- **Warenkorb in der Session** überlebt einen Moduswechsel. Er referenziert nur Produkte (gemeinsam), das ist unkritisch.
- **Umschalten während laufender Checkouts** ist durch Session-Pin und modusgebundene Webhooks abgedeckt. Die Kundin sieht im Randfall eine Fehlerseite statt einer Bestätigung, die Zahlung wird trotzdem verbucht.
