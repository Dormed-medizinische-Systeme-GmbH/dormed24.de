# Plan: Sandbox und Live physisch trennen (zwei Postgres-Schemas, eine App)

> Status: **Entwurf, nicht umgesetzt.** Grundlage ist der Stand von `main` nach PR #10 (Oktober 2026).

## Ziel

Eine Applikation, zwei vollständig getrennte Instanzen auf Datenbankebene:

- **Schema `live`** und **Schema `sandbox`** sind eine **1:1-Spiegelung** derselben Struktur. *Jede* Tabelle existiert in beiden: Kunden, Adressen, Passkeys, Produkte, Bilder, Varianten, Hersteller, Versandarten, Settings, Bestellungen, Zahlungen, Bewertungen, Sessions, Cache, Jobs und die Migrations-Tabelle.
- Kein gemeinsames Schema, keine gemeinsamen Daten. Wer sich in der Sandbox registriert, existiert in Live nicht und umgekehrt. Die Sandbox hat ihren **eigenen Katalog**.
- Shop **und** Admin arbeiten immer nur im Schema des aktiven Modus. Nach dem Zurückschalten auf Live ist nichts aus der Sandbox zu sehen.
- Gemeinsam bleibt nur, was ohnehin nicht in der Datenbank liegt: der Code, `config/categories.php` und der Admin-Zugang aus der Env (`ADMIN_EMAIL`/`ADMIN_PASSWORD`). Der Admin-Umzug in die Env zahlt sich hier aus, denn es gibt keine Admin-Tabelle, die man spiegeln oder synchronisieren müsste.

## Modus: bleibt wie heute

Der Modus kommt weiterhin aus `App\Support\PaymentMode`, es gibt kein zusätzliches Flag:

| `APP_ENV` | `APP_DEBUG` | Modus | Schema |
|---|---|---|---|
| `production` | `false` | `live` | `live` |
| jede andere Kombination | | `sandbox` | `sandbox` |

`PaymentMode::current()` steuert damit künftig PayPal, SMTP, den Sandbox-Banner **und** das Datenbank-Schema. Das ist ein einziger Schalter für alles.

> **Hinweis:** In die Sandbox schalten heißt auf Produktion `APP_DEBUG=true`. Laravel zeigt dann bei Fehlern Stacktraces inklusive Env-Ausschnitten im Browser. Die Sandbox ist in der Zeit also nicht für Fremde gedacht. Gegenmittel, falls nötig: Die Sandbox-Instanz während Testphasen per Basic-Auth oder IP-Liste abschotten (siehe Phase 6, optional).

## Ausgangslage im Code

| Thema | Heute |
|---|---|
| Datenbank | Eine Connection, `search_path = public`. `.env.example` steht auf SQLite. **Welche DB auf dem Server läuft, muss vorab geprüft werden.** |
| Kennzeichnung | `orders.is_test` wird gesetzt, aber nirgends gefiltert. Entfällt mit diesem Plan. |
| PayPal | Zugangsdaten und Webhook-ID kommen pro Modus aus `config/paypal.php`. Es gibt **eine** Webhook-Route `/paypal/webhook`, die mit der Webhook-ID des *aktiven* Modus prüft. |
| Session, Cache, Queue | alle `database`-Treiber |
| Dateien | Produktbilder liegen auf der `public`-Disk (`storage/app/public`) |
| Mails | synchron |
| Tests/CI | SQLite in-memory, kein Postgres in `tests.yml` |

## Architektur

### Zwei Connections, Default nach Modus

`config/database.php` bekommt zwei Postgres-Connections auf **dieselbe** Datenbank, die sich nur im `search_path` unterscheiden:

```php
'live' => [... 'driver' => 'pgsql', 'search_path' => 'live'],
'sandbox' => [... 'driver' => 'pgsql', 'search_path' => 'sandbox'],
```

- Im `search_path` steht **nur** das eigene Schema, kein `public`. Eine Abfrage kann das andere Schema also nicht versehentlich erreichen. Eine nicht existierende Tabelle ist ein harter Fehler, kein stiller Fallback.
- `database.default` wird beim Booten auf `PaymentMode::current()` gesetzt (`App\Support\ShopInstance::boot()` im `AppServiceProvider`, neben `ModeMailConfiguration::apply()`). Danach muss kein Model, keine Query und keine Relation etwas davon wissen. Joins, `whereHas` und Fremdschlüssel bleiben unverändert, weil alles im selben Schema liegt.
- **Absicherung:** Nach dem Verbindungsaufbau prüft `SHOW search_path`, ob genau das erwartete Schema gesetzt ist. Andernfalls gibt es eine Exception, bevor irgendetwas gelesen oder geschrieben wird.
- Ein DB-Benutzer pro Schema (optional, empfohlen): `shop_live` darf nur auf `live`, `shop_sandbox` nur auf `sandbox`. Dann ist die Trennung auch durch Postgres-Rechte erzwungen, nicht nur durch die Konfiguration.

### Was sonst noch pro Instanz getrennt werden muss

| Bereich | Maßnahme | Warum |
|---|---|---|
| Sessions | liegen in `sessions` im jeweiligen Schema; zusätzlich **eigener Cookie-Name** pro Modus (`dormed24_session_live` / `_sandbox`) | Sonst gilt eine Sandbox-Session mit `customer_id=5` nach dem Umschalten als Login von Live-Kunde 5. |
| Remember-Me / Passkeys / 2FA | liegen in den Kunden-Tabellen, sind also automatisch getrennt | — |
| Cache | `cache` im jeweiligen Schema; zusätzlich Cache-Prefix pro Modus | auch falls der Treiber später auf Redis/File wechselt |
| Queue | `jobs` im jeweiligen Schema; zwei Queue-Connections `database_live` / `database_sandbox`, **zwei dauerhaft laufende Worker** | Ein Job wird in der Instanz verarbeitet, in der er entstanden ist, unabhängig vom gerade aktiven Modus. |
| Dateien | eigene Disk-Wurzel pro Modus: `storage/app/public/live/…` und `…/sandbox/…` (bzw. zwei Disks) | Der Sandbox-Katalog hat eigene Bilder. Löschen in der Sandbox darf kein Live-Bild treffen. |
| Settings | `settings` im jeweiligen Schema | Shopname, Shop-Mail usw. können in der Sandbox abweichen. |
| PayPal / SMTP | bereits pro Modus aus der Env | — |
| Logs | Log-Kontext `mode` an jeden Eintrag hängen (`Log::shareContext`) | damit sich Logs der beiden Instanzen auseinanderhalten lassen |

### PayPal: Webhooks unabhängig vom aktiven Modus

Webhooks kommen zeitversetzt. Szenario: Eine Sandbox-Zahlung ist offen, dann wird auf Live geschaltet, danach kommt der Sandbox-Webhook.

- Zwei Routen: `POST /paypal/webhook/sandbox` und `POST /paypal/webhook/live`.
  - Jede prüft mit Zugangsdaten und Webhook-ID **ihrer** Instanz und setzt für diesen Request die Default-Connection auf **ihr** Schema, unabhängig von `PaymentMode::current()`.
  - Die URLs werden in den beiden PayPal-Apps (Sandbox- und Live-Dashboard) hinterlegt.
  - `/paypal/webhook` bleibt übergangsweise als Alias auf Live.
- `PayPalService` bekommt die Instanz als Parameter, statt intern `PaymentMode::current()` aufzurufen.
- **Rückkehr von PayPal** (`paypal.after-payment`): Hat jemand während einer laufenden Zahlung umgeschaltet, gibt es wegen der getrennten Session-Cookies keine gültige Session mehr. Der Kunde landet auf einer neutralen Seite, und die Zahlung wird trotzdem über den Webhook der richtigen Instanz verbucht.

### Umschalten: was dabei passiert

Weil alles am Modus hängt, ist das Umschalten (`.env` ändern, dann `php artisan optimize`) gleichzeitig ein Wechsel der kompletten Instanz:

- Alle Besucher und der Admin sind ausgeloggt, weil der Cookie-Name wechselt.
- Der Shop zeigt den Katalog der neuen Instanz.
- Die Worker beider Instanzen laufen weiter, Webhooks beider Instanzen kommen weiter an.
- Ein `queue:restart` ist **nicht** nötig, weil die Worker fest an ihre Instanz gebunden sind.

## Migrationen und Deploy

- Jede Migration läuft **in beiden Schemas**. Damit ist die Struktur per Definition identisch, und kein Entwickler muss an „beide Schemas“ denken.
  - Deploy: `php artisan migrate --database=live --force` **und** `php artisan migrate --database=sandbox --force` (Anpassung in `.github/workflows/deploy.yml`).
  - Alternativ ein Befehl `instances:migrate`, der beides nacheinander macht und abbricht, sobald eins fehlschlägt.
- Jedes Schema hat seine eigene `migrations`-Tabelle und weiß selbst, was gelaufen ist.
- Ein Test vergleicht die Spalten aller Tabellen beider Schemas, als Schutz falls doch einmal nur eins migriert wurde.
- Seeder (`ProductSeeder` usw.) bekommen eine Instanz-Option. Der Schutz „kein Wipe in Produktion“ gilt weiterhin für `live`.

## Sandbox-Katalog befüllen

Die Sandbox startet leer. Damit man realistisch testen kann:

- Befehl `php artisan sandbox:copy-catalog` kopiert Hersteller, Produkte, Varianten, Bilder (Dateien mit), Versandarten und unkritische Settings **von Live nach Sandbox**.
  - **Nur in diese Richtung.** Eine Gegenrichtung gibt es bewusst nicht.
  - Ein vorhandener Sandbox-Katalog wird vorher geleert, nur nach Bestätigung.
- Befehl `php artisan sandbox:reset` leert das komplette Sandbox-Schema (`DROP SCHEMA sandbox CASCADE` + `CREATE SCHEMA` + Migrationen) und das Sandbox-Bilderverzeichnis. Danach optional `sandbox:copy-catalog`.
  - Er verweigert, wenn die Default-Connection gerade `live` ist und nicht `sandbox`. Gemeint ist die Prüfung der Connection, nicht des Modus; das hier ist das wichtigste Sicherheitsnetz.

## Bestandsdaten überführen (einmalig)

Voraussetzung: Produktion läuft auf Postgres. Wenn nicht, kommt zuerst Phase 0.

1. **Backup** der kompletten Datenbank (`pg_dump`).
2. `CREATE SCHEMA live; CREATE SCHEMA sandbox;`
3. Alle Tabellen aus `public` per `ALTER TABLE public.<t> SET SCHEMA live` verschieben. Indizes, Sequenzen und Fremdschlüssel wandern mit, IDs bleiben erhalten.
4. Im Schema `sandbox` alle Migrationen frisch laufen lassen. Die Sandbox ist dann leer.
5. **Bisherige Testbestellungen** (`is_test = true`) liegen jetzt in `live`.
   - **Empfehlung:** nach dem Backup löschen, samt `order_items` und `payments`.
   - In die Sandbox verschieben geht nicht sinnvoll, weil sie auf Live-Kunden und Live-Produkte zeigen.
6. Spalte `is_test` entfernen, das Schema ist jetzt die Kennzeichnung.
7. Dateien: `storage/app/public/*` nach `storage/app/public/live/` verschieben, Pfade in `product_images` anpassen.
8. Prüfen, dann umschalten. Bricht ein Schritt ab, bleibt `public` unangetastet. Erst ganz am Ende wird es geleert.

Diese Überführung ist **ein Wartungsfenster mit Runbook**, keine automatisch laufende Migration. `SET SCHEMA` auf allen Tabellen gehört nicht in `migrate --force` bei jedem Deploy.

## Tests und CI

- Die Logik ist Postgres-spezifisch. SQLite kennt keine Schemas. Die CI braucht deshalb einen **Postgres-Service** in `.github/workflows/tests.yml`. Das ist eine Infrastruktur-Änderung und braucht Freigabe.
- **Empfehlung:** Die ganze Suite läuft künftig gegen Postgres, mit den beiden Schemas. Dann testen wir, was in Produktion läuft, und nicht SQLite. Lokal geht das per `sail` oder einem lokalen Postgres.
- Neue Tests:
  - **Shop:** Im Sandbox-Modus landet eine Bestellung in `sandbox.orders`, `live.orders` bleibt unberührt, und umgekehrt.
  - **Katalog:** Ein Produkt, das nur in Live existiert, ist im Sandbox-Shop 404 und erscheint nicht in Listen, Kategorien oder Sitemap.
  - **Kunden:** Login mit Live-Zugangsdaten schlägt im Sandbox-Modus fehl, die Registrierung landet im richtigen Schema.
  - **Session:** Ein Session-Cookie der einen Instanz wird in der anderen nicht akzeptiert.
  - **Admin:** Listen, Dashboard und Kundendetails zeigen nur die aktive Instanz.
  - **Webhooks:** Beide Routen verbuchen ins eigene Schema, auch wenn der aktive Modus der andere ist.
  - **Queue:** Ein Job, der in der Sandbox dispatcht wurde, läuft gegen `sandbox`.
  - **Dateien:** Ein Bild-Upload in der Sandbox landet unter `sandbox/`.
  - **Struktur:** Beide Schemas haben identische Tabellen und Spalten.
  - **Absicherung:** Ein falscher `search_path` führt zur Exception.
  - **Befehle:** `sandbox:reset` verweigert gegen `live`, `sandbox:copy-catalog` kopiert nur Live → Sandbox.

## Umsetzungsreihenfolge

| Phase | Inhalt | Ergebnis |
|---|---|---|
| 0 | Server-DB prüfen; falls nötig Umzug auf Postgres | Postgres als Basis |
| 1 | CI und lokale Tests auf Postgres umstellen | Tests laufen gegen die echte DB |
| 2 | PayPal: Webhook-Route pro Instanz, `PayPalService` mit Instanz-Parameter | Webhooks modusunabhängig korrekt |
| 3 | `ShopInstance`: zwei Connections, Default nach Modus, Absicherung, Session-Cookie, Cache-Prefix, Queue-Connections, Disk pro Instanz, Log-Kontext | Mechanik steht, zunächst mit leerer Sandbox |
| 4 | Deploy migriert beide Schemas, Strukturvergleich-Test | Schemas bleiben synchron |
| 5 | Wartungsfenster: Bestandsdaten nach `live` überführen (Runbook oben) | Produktion läuft getrennt |
| 6 | `sandbox:copy-catalog`, `sandbox:reset`, optional Zugangsschutz für die Sandbox | komfortabel testen |

Jede Phase ist ein eigener PR. Phase 2 lohnt sich auch ohne den Rest.

## Risiken und offene Punkte

- **Stacktraces im Sandbox-Modus auf Produktion** (wegen `APP_DEBUG=true`), siehe Hinweis oben.
- **Rohes SQL mit Schema-Präfix** (`live.` oder `public.`) umgeht die Trennung. Grep-Regel im Review, und der `search_path` enthält bewusst nur das eigene Schema.
- **Doppelte Migrationszeit** beim Deploy, bei der aktuellen Größe vernachlässigbar.
- **Backup/Restore** muss beide Schemas umfassen. `pg_dump` ohne `-n` macht das, ein Restore lässt sich pro Schema einschränken.
- **Zwei dauerhafte Queue-Worker** auf dem Server (Supervisor/systemd-Eintrag pro Instanz). Heute gibt es keine Queue-Jobs, Mails gehen synchron. Kurzfristig reicht es deshalb, das vorzubereiten.
- **Sitemap und SEO:** Im Sandbox-Modus liefert die Domain einen anderen Katalog aus. Während einer Sandbox-Phase deshalb `noindex` setzen, damit Suchmaschinen keinen Testkatalog übernehmen.
