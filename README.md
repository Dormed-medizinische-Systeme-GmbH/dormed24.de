# dormed24-Shop

Onlineshop für Medizintechnik (dormed 24) — Ablösung für Shopware.
**Stack:** Laravel 13 · Inertia v3 · Svelte 5 · Tailwind 4 · PHPUnit 12 · SQLite (Default, `DB_CONNECTION`) · PayPal (srmklive/paypal) · `npm run dev` (dev)

Dieses README ist die übergreifende Landkarte: Was der Shop tut, wie die
Kern-Flows laufen und wo was liegt. **Konvention: Wer einen Flow ändert,
zieht dieses Dokument nach.**

---

## Dokumenten-Landkarte

| Dokument | Zweck |
| --- | --- |
| `README.md` | Diese Übersicht — Flows, Architektur, Betrieb |
| `AGENTS.md` | Detail-Kontext für KI-Agenten (Layouts, Frontend-Konventionen, Routen) |
| `database/SCHEMA.md` | Vollständiges DB-Schema — bei jeder Migration aktualisieren |
| `ANALYSE.md` | Offene Audit-Punkte + bewusste Entscheidungen (erledigte Punkte werden entfernt) |
| `TODO.md` | Offener Backlog (Sicherheit, UI/UX, Ideen) |

---

## Architektur auf einen Blick

Zwei getrennte Bereiche mit **zwei unabhängigen Auth-Guards**:

| Bereich | Guard | Model | Login | Besonderheiten |
| --- | --- | --- | --- | --- |
| Shop-Frontend | `web` | `Customer` | `/login` (Fortify) | 2FA, Passkeys, E-Mail-Verifikation |
| Admin-Panel | `admin` | `EnvAdmin` (aus der Env) | `/admin/login` | genau ein Admin über `ADMIN_EMAIL` / `ADMIN_PASSWORD` (Hash via `php artisan admin:hash-password`) |

Zentrale Klassen (die „Geschäftslogik-Ecke"):

| Klasse | Verantwortung |
| --- | --- |
| `app/Support/Cart/CartService.php` | Warenkorb: Session-State, Live-Preise, Varianten, Versand-/Zahlarten; liefert das `Cart`-DTO (Summen in Cents, `toArray()` fürs Frontend) |
| `app/Support/Orders/OrderManager.php` | Cart → Order (transaktional), `markPaid()` (idempotent), Bestätigungsmails, Summen-Berechnung |
| `app/Services/PayPalService.php` | PayPal-REST-Client (Zugangsdaten aus der Env, `config/paypal.php`), Webhook-Verifikation, Refunds |
| `app/Support/PaymentMode.php` | sandbox/live — live nur bei `APP_ENV=production` **und** `APP_DEBUG=false`; steuert PayPal-/SMTP-Zugangsdaten und Sandbox-Banner |
| `config/categories.php` | Feste Produktkategorien (Reihenfolge = Navigation), Produkte speichern `category_slug` |
| `config/specialties.php` | Feste Fachrichtungen, nur für Kategorien mit `has_specialties` (Ultraschallsysteme/-sonden); Produkte speichern die Slugs als JSON-Liste (`products.specialties`) |
| `app/Support/ModeMailConfiguration.php` | Übernimmt beim Booten die SMTP-Zugangsdaten des aktiven Modus aus der Env in den Mailer |
| `app/Models/Setting.php` | Key-Value-Shop-Settings in DB (Name, Kontakt, Bankverbindung), pro Request memoisiert. PayPal/SMTP **nicht** hier, sondern in der Env |
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
/checkout (Cart) → /checkout/confirm (Adresse, AGB, Zahlart)
  → POST /checkout/submit (Hold-to-confirm-Button)
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
Confirm-Seite (Zahlart PayPal) → PayPal-JS-SDK-Button
  → POST /paypal/order/create   (Order pending + Payment CREATED; storniert
                                 eigene alte pending-PayPal-Orders)
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
(z. B. `log`) aktiv. Absendername ist der Shop-Name aus den Settings.
Admin-Empfänger der Bestell-Benachrichtigung: fest die
Kontakt-Mail-Adresse aus Setting `shop.email` (dieselbe, die auch im
Shop-Frontend angezeigt wird) — kein separates Feld, kein Fallback.

### 7. Settings & Zugangsdaten

- **Env (Server-`.env`), nicht in der Datenbank:**
  - Admin-Zugang: `ADMIN_EMAIL` + `ADMIN_PASSWORD` (bcrypt-Hash, erzeugen mit
    `php artisan admin:hash-password`). Es gibt genau einen Admin, keine
    Admin-Tabelle.
  - PayPal: `PAYPAL_SANDBOX_*` und `PAYPAL_LIVE_*` (Client-ID, Secret,
    Merchant-ID, Webhook-ID; Live zusätzlich `PAYPAL_LIVE_APP_ID`), siehe
    `config/paypal.php`.
  - SMTP: `MAIL_SANDBOX_*` und `MAIL_LIVE_*` (Host, Port, Benutzer, Passwort,
    Absenderadresse), siehe `config/mail.php` → `modes`.
- **Datenbank (`settings`-Tabelle, Pflege im Admin):** nur noch Shop-Daten wie
  Name, Kontakt (`shop.email`) und Bankverbindung.
- **Code (`config/`):** Kategorien (`categories.php`) und Fachrichtungen
  (`specialties.php`). Änderungen laufen über Deploy, nicht über den Admin.
- sandbox/live: live nur bei `APP_ENV=production` **und** `APP_DEBUG=false`,
  sonst sandbox (kein Admin-Override). Der Modus wählt das passende
  Zugangsdaten-Set für PayPal und SMTP. In der Sandbox zeigt das Frontend
  einen Banner.
- Umzug von Altdaten: `php artisan settings:legacy-credentials` gibt früher in
  der Datenbank gespeicherte PayPal-/SMTP-Zugangsdaten als Env-Zeilen aus
  (nur auf dem Server ausführen, enthält Geheimnisse).

---

## Entwicklung

```bash
composer run dev          # Laravel + Vite parallel (oder: npm run dev)
php artisan test --compact            # Testsuite (PHPUnit)
vendor/bin/pint --dirty               # PHP-Codestyle (Pflicht vor Commit)
vendor/bin/phpstan analyse            # Level 7, Baseline — muss grün bleiben
npm run lint && npm run types:check   # ESLint + svelte-check
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

## Betrieb / Launch-Checkliste

1. `php artisan storage:link` — Produktbilder liegen auf dem public-Disk.
2. `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`; `config:cache`/`route:cache` sind safe.
3. Env setzen: `ADMIN_EMAIL`/`ADMIN_PASSWORD`, `PAYPAL_LIVE_*` inkl. **Webhook-ID** (ohne sie werden alle Webhooks abgelehnt — nur im Log sichtbar) und `MAIL_LIVE_*`; danach `php artisan optimize`. Details siehe Abschnitt 7.
4. Im Admin unter Einstellungen `shop.email` pflegen (Kontakt- **und** Bestell-Benachrichtigungsadresse). Seeder mit Default-Passwörtern laufen nur außerhalb von Produktion.
5. DB-Backup-Strategie klären (siehe `ANALYSE.md`).
6. Scheduler-Cron aktuell **nicht nötig** (keine Scheduled Tasks).
7. Kein Queue-Worker nötig — Mails laufen synchron (siehe Abschnitt „Mails" oben).
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
