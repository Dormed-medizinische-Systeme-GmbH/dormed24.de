# dormed24-Shop

Onlineshop für Medizintechnik (dormed 24) — Ablösung für Shopware.
**Stack:** Laravel 13 · Inertia v3 · Svelte 5 · Tailwind 4 · PHPUnit 12 · Postgres + Redis (Queue, Cache, Sessions; lokal per `compose.dev.yaml`) · PayPal (srmklive/paypal) · `npm run dev` (dev)

Dieses README ist die übergreifende Landkarte: Was der Shop tut, wie die
Kern-Flows laufen und wo was liegt. **Konvention: Wer einen Flow ändert,
zieht dieses Dokument nach.**

---

## Dokumenten-Landkarte

| Dokument | Zweck |
| --- | --- |
| `README.md` | Diese Übersicht — Flows, Architektur, Betrieb |
| `AGENTS.md` | Kontext für KI-Agenten: Konventionen, Shop-URLs, Zahlungs-Regeln; verweist auf die übrigen Dokumente |
| `.docs/deployment.md` | CI-Gates, Deploy, Server, SSR, Sandbox-Reset |
| `database/SCHEMA.md` | Vollständiges DB-Schema — bei jeder Migration aktualisieren |
| `ANALYSE.md` | Offene Audit-Punkte + bewusste Entscheidungen (erledigte Punkte werden entfernt) |
| `TODO.md` | Offener Backlog (Sicherheit, UI/UX, Ideen) |

---

## Architektur auf einen Blick

Zwei getrennte Bereiche mit **zwei unabhängigen Auth-Guards**:

| Bereich | Guard | Model | Login | Besonderheiten |
| --- | --- | --- | --- | --- |
| Shop-Frontend | `web` | `Customer` | `/login` (Fortify) | 2FA, Passkeys, E-Mail-Verifikation |
| Admin-Panel | `admin` | `EnvAdmin` (aus der Env) | `/admin/login` | genau ein Admin über `ADMIN_EMAIL` / `ADMIN_PASSWORD` (bcrypt-Hash, Klartext wird abgelehnt) |

Zentrale Klassen (die „Geschäftslogik-Ecke"):

| Klasse | Verantwortung |
| --- | --- |
| `app/Support/Cart/CartService.php` | Warenkorb: Session-State, Live-Preise, Varianten, Versandarten; liefert das `Cart`-DTO (Summen in Cents, `toArray()` fürs Frontend) |
| `app/Support/Orders/OrderManager.php` | Cart → Order (transaktional), alle Statusübergänge (`transitionTo`, `markPaid()` idempotent), Bestätigungsmails |
| `app/Support/Orders/OrderStatus.php` | Bestellstatus + erlaubte Übergänge (`PaymentStatus`, `PaymentMethod` daneben) |
| `app/Support/Orders/OrderSummary.php` | Positionen und Summen einer Bestellung (MwSt. über `Money::netCents`) |
| `app/Support/Orders/PaymentReconciliation.php` | Zahlungsabgleich: Anlegen, Capture, Erstattung und Webhooks → Payment-Status, Betragsabgleich, Bestell-Übergänge über den `OrderManager` |
| `app/Support/Orders/Gateway/PaymentGateway.php` | Seam zum Zahlungsdienst; neutrale Ergebnisse (`CaptureResult`, `RefundResult`, `WebhookEvent`, …) daneben |
| `app/Services/PayPalGateway.php` | PayPal-Adapter: übersetzt die PayPal-Antworten, einzige Stelle, die PayPal-JSON liest |
| `app/Services/PayPalService.php` | PayPal-REST-Client über das SDK (Zugangsdaten aus der Env, `config/paypal.php`), Webhook-Verifikation, Refunds; gibt rohes JSON zurück |
| `app/Support/PaymentMode.php` | sandbox/live — live nur bei `APP_ENV=production` **und** `APP_DEBUG=false`; steuert PayPal-/SMTP-Zugangsdaten und Sandbox-Banner |
| `config/categories.php` | Feste Produktkategorien (Reihenfolge = Navigation), Produkte speichern `category_slug` |
| `config/specialties.php` | Feste Fachrichtungen, nur für Kategorien mit `has_specialties` (Ultraschallsysteme/-sonden); Produkte speichern die Slugs als JSON-Liste (`products.specialties`) |
| `app/Support/ModeMailConfiguration.php` | Übernimmt beim Booten die SMTP-Zugangsdaten des aktiven Modus aus der Env in den Mailer |
| `app/Support/ShopContact.php` | Kontakt und Bankverbindung aus `config/shop.php` (Telefon/Fax als `PhoneNumber` mit Anzeige und `tel:`-Link), Shopname = `APP_NAME`. Keine Konfiguration im Admin |
| `app/Http/Middleware/HandleInertiaRequests.php` | Shared Props: Cart, Auth, Kontakt, Kategorien, Sandbox-Banner |

---

## Kern-Flows

### 1. Warenkorb (Session-basiert, Live-Preise)

- State liegt in der Session (`SessionCartStore`), Format: `items[lineKey => quantity]`.
- **Line-Key:** `productId` (ohne Variante) oder `productId:variantId` — dasselbe
  Produkt kann pro Variante als eigene Zeile liegen.
- Preise/Namen werden **bei jedem Aufruf live** aus der DB gelesen (kein
  Preis-Snapshot in der Session). Der Snapshot entsteht erst in der Order.
- **Varianten:** absolutes Preismodell — `product_variants.price` ist der volle
  Endpreis. Produkte *mit* Varianten sind nur als konkrete Variante bestellbar
  (Validierung im `AddCartItemRequest`). Das Varianten-Label wird Teil des
  Zeilennamens („Produkt – Label") und wandert so überall hin (UI, Order, Mails).
  Pflege-Konvention: `products.price` sollte dem Preis der Default-Variante
  entsprechen (Listen zeigen den Produktpreis).
- Nicht verfügbare/gelöschte Produkte und gelöschte Varianten machen die Zeile
  `is_available = false` → Checkout blockiert serverseitig.

### 2. Bestellen — Voraussetzung: bestätigte E-Mail

- Registrierung verschickt eine Verifikationsmail (deutscher Text,
  `FortifyServiceProvider::configureVerificationMail()`).
- **Bestell-Hebel:** `verified`-Middleware auf `checkout.confirm`,
  `checkout.address.update`, `checkout.submit`, `paypal.order.create`,
  `paypal.order.capture`. Ohne bestätigte Adresse entsteht keine Order —
  Stöbern und Warenkorb bleiben frei.
- E-Mail-Änderung im Profil setzt die Verifikation zurück → Bestellen gesperrt,
  bis die neue Adresse bestätigt ist.

### 3. Checkout „Kauf auf Rechnung" (invoice)

```
/checkout (Cart) → /checkout/confirm (Adresse, AGB, Buttons je Zahlart)
  → POST /checkout/submit (Button „Kaufen auf Rechnung“, normaler Klick)
  → Order status=pending + Items (Snapshot, DB-Transaktion)
  → Bestätigungsmail an Kunde (inkl. Bankverbindung) + Admin-Benachrichtigung
  → Cart wird geleert → /checkout/success
```

- Order bleibt **bewusst `pending`**, bis die Überweisung eintrifft — der Admin
  setzt sie im Bestelldetail auf `paid` (optional mit erneuter Kundenmail).
- Deshalb gibt es **kein automatisches Cleanup** von pending-Orders
  (bewusste Entscheidung, siehe `ANALYSE.md`).

### 4. Checkout PayPal

```
Confirm-Seite → PayPal-JS-SDK-Button (PayPal, Lastschrift oder Kreditkarte)
  → POST /paypal/order/create   (funding_source = geklickte Zahlart; Order
                                 pending + Payment CREATED; storniert eigene
                                 alte pending-PayPal-Orders aller drei Zahlarten)
  → Kunde bestätigt bei PayPal
  → POST /paypal/order/capture  (Ownership-Check, Betragsabgleich!)
  → Payment COMPLETED + OrderManager::markPaid() → Mails → Cart leer
  → /checkout/success?paypal_order_id=…
```

- **Zweiter Pfad:** Redirect-Rückkehr `/paypal/after-payment?token=…` captured
  ebenfalls (für den Fall, dass das JS-Capture nicht lief).
- **Webhooks** (`/paypal/webhook`, signaturverifiziert, CSRF-ausgenommen):
  `CAPTURE.COMPLETED` → markPaid (idempotent, atomarer Statuswechsel → keine
  Doppel-Mails); `CAPTURE.REFUNDED`/`DENIED` → Payment **und** Order werden
  konsistent gesetzt (Capture-ID steckt beim Refund im `up`-Link!).
- Admin-Refund im Bestelldetail nutzt dieselbe Semantik.

### 5. Bestellstatus-Lebenszyklus

`pending` → `paid`, sowie `cancelled` / `failed` / `refunded`. `is_test`
markiert Sandbox-Orders (aus `PaymentMode::isLive()`), fließt aber **nicht**
in den Dashboard-Umsatz ein — der zählt schlicht alle `paid`-Orders. Der
Filter wurde entfernt: `PaymentMode` hängt ausschließlich an `APP_ENV` und `APP_DEBUG`, ohne
Admin-Override kann in Produktion strukturell keine Sandbox-Order entstehen,
die die Zahlen verfälschen könnte. Kein separater Fulfillment-Status
(`processing`/`completed`) — `paid` ist der finale Erfolgszustand.

- **Kontolöschung** durch den Kunden entkoppelt Orders nur
  (`customer_id = null`, FK `ON DELETE SET NULL`) — Historie bleibt für
  GoBD/§147 AO erhalten; Adress-/Preisdaten sind ohnehin als Snapshot auf der
  Order.

### 6. Mails — synchron

`OrderConfirmationMail` + `NewOrderMail` werden synchron beim Request
verschickt (kein `ShouldQueue`, kein Queue-Worker nötig). Bei aktuell max. ~5
Mails/Tag ist der Request-Overhead vernachlässigbar; eine spätere Umstellung
auf Queue ist bei Bedarf ein kleiner Schritt (Interface + `Queueable`-Trait
zurück auf die beiden Mailables, Worker-Prozess aufsetzen). SMTP-Zugangsdaten
kommen aus der Env, je Modus getrennt (`MAIL_SANDBOX_*` / `MAIL_LIVE_*`, siehe
Abschnitt 7). Ist für den aktiven Modus kein Host gesetzt, bleibt `MAIL_MAILER`
(z. B. `log`) aktiv. Absendername ist `MAIL_FROM_NAME` (Standard: `APP_NAME`).
Admin-Empfänger der Bestell-Benachrichtigung: fest die
Kontakt-Mail-Adresse aus `config/shop.php` (`shop.contact.email`, dieselbe,
die auch im Shop-Frontend angezeigt wird) — kein separates Feld, kein Fallback.

### 7. Settings & Zugangsdaten

- **Env (Server-`.env`), nicht in der Datenbank:**
  - Admin-Zugang: `ADMIN_EMAIL` + `ADMIN_PASSWORD` (bcrypt-Hash in einfachen
    Anführungszeichen, erzeugen mit
    `php artisan tinker --execute 'echo Hash::make("…");'`). Es gibt genau einen Admin, keine
    Admin-Tabelle.
  - PayPal: `PAYPAL_SANDBOX_*` und `PAYPAL_LIVE_*` (Client-ID, Secret,
    Merchant-ID, Webhook-ID; Live zusätzlich `PAYPAL_LIVE_APP_ID`), siehe
    `config/paypal.php`.
  - SMTP: `MAIL_SANDBOX_*` und `MAIL_LIVE_*` (Host, Port, Benutzer, Passwort,
    Absenderadresse), siehe `config/mail.php` → `modes`.
- **Code (`config/`):** Kontakt (E-Mail, Telefon und Fax als Vorwahl +
  Rufnummer) und Bankverbindung (`shop.php`), Kategorien (`categories.php`)
  und Fachrichtungen (`specialties.php`). Der Shopname ist `APP_NAME`.
  Änderungen laufen über Deploy, nicht über den Admin; im Admin gibt es keine
  Konfiguration (`.docs/adr/0004-keine-konfiguration-im-admin.md`).
- sandbox/live: live nur bei `APP_ENV=production` **und** `APP_DEBUG=false`,
  sonst sandbox (kein Admin-Override). Der Modus wählt das passende
  Zugangsdaten-Set für PayPal und SMTP. In der Sandbox zeigt das Frontend
  einen Banner.

---

## Entwicklung

```bash
composer run dev          # Laravel + Vite parallel (oder: npm run dev)
php artisan test --compact            # Testsuite (PHPUnit)
vendor/bin/pint --dirty               # PHP-Codestyle (Pflicht vor Commit)
vendor/bin/phpstan analyse            # Level 7, Baseline — muss grün bleiben
npm run lint && npm run types:check   # ESLint + svelte-check
npm run test                          # Frontend-Tests (Vitest, jsdom; *.test.ts neben dem Code)
php artisan wayfinder:generate        # Nach Routen-Änderungen (TS-Routen)
npm run build                         # Produktions-Assets (Client)
npm run build:ssr                     # Client + SSR-Bundle (bootstrap/ssr/app.js)
```

- **SSR ist lokal aus** (`INERTIA_SSR_ENABLED=false`) — zum Entwickeln reicht
  Client-Rendering. Produktions-Setup: siehe Launch-Checkliste Punkt „SSR".

- **Workflow:** Findings/Aufgaben laufen über `ANALYSE.md` — erledigte
  Punkte werden dort entfernt, bewusste Entscheidungen bleiben unten gelistet.
- Feature-Tests sind die Wahrheit: jeder Fix bekommt einen Test. CSRF ist in
  Tests deaktiviert — Browser-relevante Dinge (PayPal-Button, Fetches) einmal
  manuell gegen die Sandbox klicken.
- Frontend-Konventionen und Layout-Auflösung: siehe `AGENTS.md`.

---

## Docker-Stack: Postgres und Redis

Entscheidung: `.docs/adr/0005-postgres-und-redis-im-docker-stack.md`.

- **Lokal:** `docker compose -f compose.dev.yaml up -d` startet Postgres und Redis auf `127.0.0.1`; die Werte in `.env.example` passen dazu. Die App läuft weiter mit `npm run dev`. PHP braucht lokal die Erweiterungen `pdo_pgsql` und `redis` (z. B. `php8.4-pgsql`, `php8.4-redis`).
- **Server:** `compose.yaml` startet sechs Container, ein Prozess pro Container:
  - `app` (Nginx + PHP-FPM), `queue` (`queue:work redis`) und `scheduler` (`schedule:work`) aus demselben Image (`Dockerfile.prod`, Target `app`);
  - `ssr` (Node, Target `ssr`) rendert die Inertia-Seiten serverseitig, die App spricht ihn über `INERTIA_SSR_URL=http://ssr:13714` an;
  - `postgres` und `redis` (Redis mit `noeviction`, damit nie Queue-Jobs verloren gehen).

  Daten liegen in benannten Volumes (`postgres`, `redis`, `storage`). Die App hört nur auf `127.0.0.1:APP_PORT` (Standard 8080); TLS und Domain bleiben beim Nginx auf dem Host, der per `proxy_pass` dorthin weiterleitet und `X-Forwarded-*` setzt (`TRUSTED_PROXIES=*` im Stack, siehe `config/trustedproxy.php`). Logs gehen nach stderr (`docker compose logs`).
- **Release-Ablauf:** `.env` neben `compose.yaml` (mindestens `APP_KEY`, `DB_PASSWORD`, Zugangsdaten), dann:
  1. `docker compose build` (oder fertige Images ziehen)
  2. `docker compose up -d --wait postgres redis`
  3. `docker compose run --rm app php artisan migrate --force` — Migrationen sind ein eigener Schritt, nicht Teil des Containerstarts.
  4. `docker compose up -d --wait`

  Beim Start baut jeder App-Container seine Caches (`optimize` bzw. `config:cache`); Queue, Scheduler und Artisan-Befehle laufen als `www-data`.
- **dev.dormed24.de** läuft mit diesem Stack über Coolify (`compose.coolify.yaml`, fertige Images aus GHCR, Migration als eigener `migrate`-Service). Einrichtung und Ablauf: `.docs/deployment.md`, Abschnitt „dev.dormed24.de“.
- **Umzug der SQLite-Daten (einmalig):**
  1. Stack wie oben starten (Schritt 3 legt das leere Postgres-Schema an).
  2. Alte App stoppen (keine neuen Bestellungen mehr in SQLite).
  3. `docker compose run --rm -v /pfad/zur/database.sqlite:/import.sqlite:ro app php artisan db:import-sqlite /import.sqlite` kopiert alle Daten, setzt die Sequenzen und vergleicht die Zeilenzahlen. Er bricht ab, wenn Postgres schon Daten enthält; bei einem Fehler bleibt Postgres leer.
  4. Produkt- und Newsletterbilder aus `storage/app/public` in das Volume `storage` kopieren (z. B. `docker compose cp storage/app/public/. app:/app/storage/app/public/`, danach `docker compose exec app chown -R www-data:www-data storage/app/public`) und die Produktbilder mit `docker compose exec app php artisan product-images:convert` in WebP + AVIF umwandeln.
  5. Proxy/Webserver auf den Container umstellen.

## Betrieb / Launch-Checkliste

1. `php artisan storage:link` — Produktbilder liegen auf dem public-Disk, ausschließlich als WebP + AVIF (`App\Support\Images`). Bilder, die noch als JPEG/PNG vorliegen (z. B. nach dem Datenumzug), einmalig mit `php artisan product-images:convert` umwandeln. Der Server braucht PHP `gd` mit WebP- und AVIF-Unterstützung sowie `exif`.
2. `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`; `config:cache`/`route:cache` sind safe.
3. Env setzen: `ADMIN_EMAIL`/`ADMIN_PASSWORD`, `PAYPAL_LIVE_*` inkl. **Webhook-ID** (ohne sie werden alle Webhooks abgelehnt — nur im Log sichtbar) und `MAIL_LIVE_*`; danach `php artisan optimize`. Details siehe Abschnitt 7.
4. In `config/shop.php` Kontakt (`contact.email` ist auch die Bestell-Benachrichtigungsadresse) und Bankverbindung (`bank.iban`, `bank.bic`) prüfen; Änderungen per Commit und Deploy. Seeder mit Default-Passwörtern laufen nur außerhalb von Produktion.
5. DB-Backup-Strategie klären (siehe `ANALYSE.md`).
6. Scheduler-Cron aktuell **nicht nötig** (keine Scheduled Tasks).
7. Queue-Worker und Scheduler laufen im Docker-Stack als eigene Container (`queue`, `scheduler` in `compose.yaml`). Mails laufen bisher trotzdem synchron (siehe Abschnitt „Mails" oben).
8. **SSR (Server-Side Rendering)** für Crawler/Link-Vorschauen (WhatsApp,
   Slack, LinkedIn …):
   - `npm run build:ssr` baut Client- **und** SSR-Bundle
     (`bootstrap/ssr/app.js`, gitignored — beim Deploy neu bauen).
   - `INERTIA_SSR_ENABLED=true` setzen.
   - `php artisan inertia:start-ssr` als **Dauerprozess** (Supervisor/systemd)
     neben PHP laufen lassen (Node-Prozess auf `127.0.0.1:13714`). Health:
     `php artisan inertia:check-ssr`. Fällt der Prozess aus, rendert Inertia
     automatisch clientseitig weiter — kein harter Ausfall, nur keine
     Vorschauen mehr.
   - Kein separater Einstiegspunkt nötig: `@inertiajs/vite` wrappt beim
     `--ssr`-Build automatisch `resources/js/app.ts`.

Offene Punkte vor/nach Launch: siehe `ANALYSE.md` und `TODO.md`.
