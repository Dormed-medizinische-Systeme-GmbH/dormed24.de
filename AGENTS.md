# dormed-shop — Agent Context

## IDENTITY

- **Name:** Dormed Shop Bot
- **Creature:** Assistent und DevOps-Ingenieur
- **Language:** Deutsch

> **Datenbankschema:** [`database/SCHEMA.md`](database/SCHEMA.md) — vollständige Tabellenstruktur, Spalten, Indizes und Fremdschlüssel. Muss bei jeder Schemaänderung aktualisiert werden. Immer lesen bevor Migrationen oder Modelle erstellt werden.
>
> **Projekt-Landkarte:** [`README.md`](README.md) — übergreifende Flows (Checkout, PayPal, Verifikation, Varianten, Settings), Betrieb & Launch-Checkliste. Bei Flow-Änderungen mitpflegen.

Ablösung für Shopware. Medical equipment online shop für dormed 24 (Medizintechnik). Gebaut mit Laravel 13 + Inertia v3 + Svelte 5.

## Was diese App ist

B2B/B2C-Shop für Medizintechnik (Ultraschallsysteme, Zubehör, Verbrauchsmaterialien). Zwei eigenständige Bereiche:

- **Shop-Frontend** — öffentlich, kein persistentes Layout, eigener `ShopHeader`
- **Admin-Panel** — intern, Sidebar-`AppLayout`, eigener Auth-Guard

---

## Auth: Zwei unabhängige Guards

| Guard   | Model      | Tabelle     | Login-Route    | Middleware       |
| ------- | ---------- | ----------- | -------------- | ---------------- |
| `web`   | `Customer` | `customers` | `/login`       | `auth` (Fortify) |
| `admin` | `User`     | `users`     | `/admin/login` | `ensure.admin`   |

- `Customer` — Shop-Kunden; Passkeys + 2FA via Fortify, implementiert `MustVerifyEmail`
- **Bestell-Hebel:** `verified`-Middleware auf `checkout.confirm/address/submit` und `paypal.order.create/capture` — ohne bestätigte E-Mail keine Bestellung
- `User` — interne Admin-Nutzer; eigener `LoginController`, kein Fortify; `EnsureAdmin` prüft Login **und** `is_admin`-Flag
- In Svelte: `page.props.auth.user` ist immer der aktuelle `Customer`
- TypeScript-Typ: `Customer` aus `@/types`

---

## Layout-Auflösung (`resources/js/app.ts`)

| Seitenmuster                  | Layout                              |
| ----------------------------- | ----------------------------------- |
| `Admin/*` (außer Login)       | `AppLayout` (Admin-Sidebar)         |
| `Admin/Login`, `auth/*`       | `AuthLayout`                        |
| `settings/*`                  | `CustomerLayout` + `SettingsLayout` |
| alles andere (Shop-Frontend)  | keins (standalone)                  |

Standalone-Seiten (Welcome, Products/*, Checkout/*, statische Seiten) binden `<ShopHeader>` + `<AppFooter>` selbst ein.

---

## Routen-Überblick

| Datei                      | Routen                                    |
| -------------------------- | ----------------------------------------- |
| `routes/web.php`           | Home (`/`), statische Seiten (AGB, FAQ, …), Sitemap; lädt die anderen Routedateien |
| `routes/products.php`      | `GET /products`, `/products/search`, `/products/{product}` |
| `routes/categories.php`    | `GET /{category:slug}` (Catch-all, ein Segment) |
| `routes/public/rating.php` | `POST /products/{product}/ratings` (anonym — siehe TODO) |
| `routes/checkout.php`      | Cart (`/cart/items/{product}/{variant?}`), Checkout (`confirm`/`address`/`submit` mit `auth`+`verified`), Success, Kundenbestellungen |
| `routes/paypal.php`        | `order/create` + `order/capture` (`auth`+`verified`), `after-payment`, `webhook` (signaturverifiziert, ohne CSRF) |
| `routes/admin.php`         | Admin-CRUD (Produkte inkl. Bilder/Varianten, Kategorien, Hersteller, Kunden), Bestell-Aktionen (`orders.confirm-payment`, `orders.refund`), Settings + Versandarten |
| `routes/settings.php`      | Profil, Adressen, Sicherheit (Kunde)      |

---

## Modelle & Datenbank

| Model            | Tabelle            | Hinweise                          |
| ---------------- | ------------------ | --------------------------------- |
| `Customer`       | `customers`        | Shop-Auth, Fortify, Passkeys, 2FA, `MustVerifyEmail` |
| `User`           | `users`            | Nur Admin-Auth (`is_admin`-Flag)  |
| `Product`        | `products`         | `manufacturer_id`/`category_id` FKs, `is_available`, `available()`-Scope |
| `ProductVariant` | `product_variants` | Absoluter Endpreis (`price`), `is_default`, `sort_order` |
| `ProductImage`   | `product_images`   | public-Disk, `sort_order` (0 = Hauptbild) |
| `Manufacturer`   | `manufacturers`    |                                   |
| `Category`       | `categories`       | Slug-basierte Shop-Route          |
| `Order`          | `orders`           | FK zu `customers` **nullable, ON DELETE SET NULL** (Historie überlebt Kontolöschung); Adress-Snapshots als JSON |
| `OrderItem`      | `order_items`      | Snapshot: `product_name` (inkl. Varianten-Label), `unit_price` |
| `Payment`        | `payments`         | PayPal-Transaktionen (order_id, capture_id, response_data) |
| `Rating`         | `ratings`          | Anonym (Stand jetzt), FK `products` |
| `Address`        | `addresses`        | Kunden-Stammadressen (shipping/billing, `is_default`) |
| `ShippingMethod` | `shipping_methods` | Versandarten inkl. Preis          |
| `Setting`        | `settings`         | Key-Value, sensible Keys verschlüsselt, pro Request memoisiert |

---

## Frontend-Struktur

```
resources/js/
├── pages/
│   ├── Welcome.svelte              # Startseite (Hero + Trust-Bar)
│   ├── AGB/Datenschutz/FAQ/...     # Statische Seiten
│   ├── Checkout/
│   │   ├── Index.svelte            # Warenkorb-Review mit Tabelle
│   │   ├── Confirm.svelte          # Bestellabschluss (Adresse, AGB, Zahlart, PayPal-Button)
│   │   ├── Success.svelte          # Bestellbestätigung
│   │   └── Error.svelte
│   ├── Products/
│   │   ├── Index.svelte            # Produktliste (InfiniteScroll, Sortierung)
│   │   ├── ByCategory.svelte       # Kategorie-Listing
│   │   └── Show.svelte             # Detailseite (Varianten, Zoom, Ratings, JSON-LD)
│   ├── Admin/                      # Dashboard, Products, Orders, Customers,
│   │                               # Categories, Manufacturers, Settings, Login
│   ├── auth/                       # Fortify-Auth-Seiten (inkl. VerifyEmail)
│   └── settings/                   # Profil, Adressen, Sicherheit, Bestellungen (+ Show)
├── components/
│   ├── ShopHeader.svelte           # Logo, Suche (+Empty-State), User-Dropdown, Cart-Trigger
│   ├── CartSheet.svelte            # Slide-in Warenkorb (rechts)
│   ├── PayPalButton.svelte         # PayPal-JS-SDK-Integration (fetchJson)
│   ├── AddressForm.svelte          # Wiederverwendetes Adressformular (prefix-basiert)
│   └── ui/                         # shadcn-svelte Komponentenbibliothek
├── lib/
│   ├── http.ts                     # fetchJson() — CSRF-sicherer Fetch für Nicht-Inertia-Endpoints
│   └── currency.ts / utils.ts / …
├── layouts/
│   ├── AppLayout.svelte            # Admin-Sidebar-Layout
│   ├── CustomerLayout.svelte       # Kunden-Settings-Rahmen
│   ├── AuthLayout.svelte           # Auth-Seiten
│   └── settings/Layout.svelte      # Settings-Sub-Layout
├── actions/                        # Wayfinder (Controller-Routen als TS-Funktionen)
├── routes/                         # Wayfinder (Named Routes als TS-Funktionen)
└── types/                          # auth.ts, cart.ts (CartItem inkl. line_key/variant_*), …
```

**Wichtig — CSRF:** Inertias `router.*` sendet den XSRF-Token automatisch.
Rohe `fetch()`-Aufrufe **müssen** über `fetchJson()` aus `@/lib/http` laufen
(sonst 419; es gibt kein csrf-Meta-Tag im Blade-Template).

---

## Cart/Checkout-Daten

Der Cart läuft server-seitig über **`App\Support\Cart\CartService`** (Store via `CartStore`-Contract). `CartService::cart()` liefert das vollständige Cart-Array (Items, Versand-/Zahlungsarten, Adressen, Summen) und wird in `HandleInertiaRequests` als shared Prop `cart` verteilt.

- **Session-State:** `items[lineKey => quantity]`; Line-Key = `productId` oder `productId:variantId` (Varianten = eigene Zeilen).
- **Preise live:** Namen/Preise werden bei jedem Aufruf aus der DB gelesen; der Snapshot entsteht erst in der Order (`OrderManager::createFromCart`, transaktional).
- **Varianten:** absolutes Preismodell (`product_variants.price` = Endpreis); Produkte mit Varianten nur als Variante bestellbar; Label wird Teil des Zeilennamens.
- Frontend adressiert Cart-Zeilen über `item.line_key` (each-Key) und `/cart/items/{product}/{variant?}`.

---

## Frontend-Konventionen

### Svelte 5 Runes (zwingend)

- `let { prop } = $props()` statt `export let prop`
- `$state()`, `$derived()`, `$effect()` statt `$:`
- `onclick=` statt `on:click`
- `{#snippet name(args)}` + `{@render name()}` statt `slot`

### shadcn-svelte Regeln

- `gap-*` nicht `space-y-*` / `space-x-*`
- `size-*` wenn Breite = Höhe (z.B. `size-4` statt `w-4 h-4`)
- `Sheet`, `Dialog`, `Drawer` brauchen immer einen `Title` (ggf. `class="sr-only"`)
- Keine manuellen `z-index`-Werte auf Overlay-Komponenten
- `cn()` aus `@/lib/utils` für bedingte Klassen

### Navigation

- Interne Links immer mit `<Link>` aus `@inertiajs/svelte`, nie `<a>`
- Ausnahme: externe Links oder reine Anker (`tel:`, `mailto:`, `#section`)

### Wayfinder

- Controller-Routen: `import * as ProductController from '@/actions/App/Http/Controllers/ProductController'`
- Named Routes: `import { logout } from '@/routes'`
- URL auflösen: `toUrl(editProfile())` aus `@/lib/utils`
- **`php artisan wayfinder:generate` manuell nur mit `--with-form`!** `vite.config.ts` hat `wayfinder({ formVariants: true })` — der Vite-Dev-Plugin generiert automatisch mit `.form()`-Helpern (von `<Form {...store.form()}>` in allen Fortify-Auth-Seiten benötigt, z.B. `auth/Login.svelte`). Ein manueller Aufruf **ohne** `--with-form` überschreibt diese Dateien mit einer Version ohne `.form()` → `store.form is not a function`, Seite crasht clientseitig komplett (leere Seite, kein Fehler im Server-Log, nur in der Browser-Konsole). Schon einmal passiert, siehe Git-Historie.

### Brand-Farben (Shop-Frontend)

| Name      | Wert      | Verwendung                    |
| --------- | --------- | ----------------------------- |
| Navy      | `#0d1f44` | Primäre CTAs, Hintergrundtext |
| Blue      | `#1a6bbf` | Links, Icons, Akzente         |
| Dark blue | `#1a3a5c` | Sekundärtext, Nav-Hover       |

### Button-as-Link Muster (asChild)

```svelte
<Button asChild class="...">
    {#snippet children(props)}
        <Link href="/pfad" class={props.class}>Label</Link>
    {/snippet}
</Button>
```

### SheetClose + Link (Sheet schließen + navigieren)

```svelte
<SheetClose asChild>
    {#snippet children(closeProps)}
        <Button asChild class="...">
            {#snippet children(btnProps)}
                <Link href="/pfad" class={btnProps.class} onclick={closeProps.onClick as () => void}>
                    Label
                </Link>
            {/snippet}
        </Button>
    {/snippet}
</SheetClose>
```

**Wichtig:** `SheetTrigger asChild` reicht `props.onclick` (Kleinbuchstaben) weiter, nicht `props.onClick`.

---

## Backup-Dateien (nicht aktiv)

- `ShopHeader-with-hover.svelte` — Mega-Menü-Variante

Nicht importiert. **Entscheidung Linus (07/2026): die Hover-Variante bleibt
erhalten und darf nicht gelöscht werden.** (Die frühere left-align-Variante
wurde 07/2026 entfernt.)

---

## Zahlungen (Payments)

Zwei Bezahlarten — mehr gibt es nicht —, ein gemeinsamer Order-/Mail-Pfad über **`App\Support\Orders\OrderManager`**.

| Bezahlart | Flow | Order-Status nach Abschluss |
| --------- | ---- | --------------------------- |
| Rechnung (`invoice`) | `CheckoutController::submit` | bleibt `pending` (Zahlung per Überweisung) |
| PayPal (`paypal`) | `PayPalController` (JS-SDK, createOrder/captureOrder) + Return-URL `paypal/after-payment` | `paid` (nach Capture) |

Regeln:

- **`OrderManager` ist die einzige Stelle**, die aus dem Cart eine Order baut (`createFromCart`, in DB-Transaktion) und „bezahlt"-Übergänge idempotent macht (atomarer Statuswechsel → keine Doppel-Mails). Neue Gateways hier andocken, nicht in den Controllern duplizieren. **Zwei getrennte Mail-Pfade, nicht austauschbar:**
  - `sendConfirmations()` / `markPaid()` — „Bestellung ist eingegangen und bezahlt": `OrderConfirmationMail` (Kunde, inkl. Bankdaten-Block falls noch unbezahlt) **+** `NewOrderMail` (Betreiber). Für Rechnung bei `submit()` direkt (Bank-Hinweis), für PayPal einmalig bei `markPaid()` nach Capture/Webhook (einziger Zeitpunkt, an dem der Betreiber informiert wird).
  - `confirmInvoicePayment()` — manuelle Zahlungseingangs-Bestätigung bei Rechnung (Admin-Button): **eigenes** `PaymentConfirmationMail`/`payment-confirmation.blade.php` (kein Bankdaten-Block, andere Formulierung), **nur an den Kunden**, nie an den Betreiber (der bestätigt es ja gerade selbst). Absichtlich kein `if/else` innerhalb eines gemeinsamen Templates — die beiden Zustände „bitte zahlen" und „danke, bezahlt" sind komplett getrennte Mailables/Views.
- **Bestellen nur mit bestätigter E-Mail** (`verified`-Middleware, siehe Auth-Abschnitt).
- **Angebotene Zahlarten sind fest verdrahtet**: Kauf auf Rechnung + PayPal (kein Admin-Setting, kein Provider-Umschalter). Labels/Reihenfolge in `config/shop.php`, Auswahl-UI: Radio auf `Checkout/Confirm`.
- **Sandbox/Live** = `App\Support\PaymentMode`, ausschließlich aus `APP_ENV` abgeleitet (production = live, sonst sandbox). Kein Admin-Override, kein Setting — im Admin unter Einstellungen → Zahlungsarten nur Zugangsdaten für beide Modi pflegbar.
- **Secrets** liegen verschlüsselt in `settings` (`Setting::$encryptedKeys`) — **Single Source of Truth**. Es gibt keine `PAYPAL_*`-env-Fallbacks mehr; Erstbefüllung nur über `SEED_PAYPAL_*` + `PaymentSeeder`.
- **Webhooks** (`/paypal/webhook`): signaturverifiziert; `CAPTURE.COMPLETED` → `markPaid`; `CAPTURE.REFUNDED`/`DENIED` setzen Payment **und** Order (Achtung: beim Refund ist `resource.id` die Refund-ID, die Capture-ID steckt im `up`-Link).
- **Benachrichtigungs-Empfänger** = fest Setting `shop.email` (dieselbe Kontakt-Mail-Adresse wie im Shop-Frontend), kein separates Feld, kein Fallback. Beide Bestellmails laufen **synchron** im Request (kein Queue-Worker nötig; bei ~5 Mails/Tag ist der Overhead vernachlässigbar).
- **Admin-Bestellaktionen** unter `Admin/Orders/Show`: `orders.confirm-payment` (einzige manuelle Status-Änderung, s.u.) + PayPal-Refund (`orders.refund`).

### Betriebsmodell — wo hört die Automatisierung auf?

Der Shop deckt **ausschließlich** Bestellannahme + Zahlungsabwicklungsschicht ab. Alles danach (Rechnungsstellung, DPD-Versand, Buchhaltung) läuft **außerhalb** des Systems, manuell im Rechnungswesen — das ist bewusst so und keine offene Baustelle:

- **PayPal-Bestellungen sind vollautomatisch und darf der Admin nicht manuell anfassen.** Status (`pending`/`paid`/`failed`/`refunded`/`cancelled`) wird ausschließlich vom Capture-Flow bzw. Webhook gesetzt (`PayPalController`). Es gibt **kein** Status-Dropdown im Admin.
- **Rechnungsbestellungen (`invoice`)**: einzige manuelle Aktion ist „Zahlungseingang bestätigen" (`OrderController::confirmPayment`, Route `orders.confirm-payment`) — setzt `pending` → `paid`, wenn eine Überweisung/Lastschrift eingegangen ist. Nur möglich, wenn `payment_method === 'invoice'` **und** Status `pending` ist; sonst 422. Kein anderer Statuswert ist über die UI erreichbar.
- **Es gibt keinen Fulfillment-Status im System** (kein „in Bearbeitung"/„versendet"/„abgeschlossen" — bewusst entfernt, siehe Git-Historie). `paid` ist der finale Erfolgszustand; Versand/Rechnung/DPD-Tracking passiert in der externen Rechnungssoftware.
- Storno (`cancelled`) über den Admin ist aktuell **nicht** vorgesehen — nur PayPal selbst (Abbruch/Timeout) oder eine zukünftige bilaterale PayPal-Storno-Anbindung dürfen das setzen.

---

## CI/CD & Deployment

**Ziel:** Auf den Server muss man **nur noch für einen Sandbox-Reset** (`migrate:fresh --seed`). Alles andere macht der Deploy automatisch beim Push auf `main`.

### Die drei Workflows (`.github/workflows/`)

| Workflow | Läuft bei | Inhalt |
| --- | --- | --- |
| `tests.yml` | Push (develop/master/workos), PR, **+ `workflow_call`** | PHPStan + PHPUnit + Build, **PHP 8.4** |
| `lint.yml` | Push (develop/master/workos), PR, **+ `workflow_call`** | Pint + ESLint + Prettier |
| `deploy.yml` | **Push auf `main`** + manuell (`workflow_dispatch`) | ruft beide Gates auf, deployt danach per SSH |

**Das „depends on":** `deploy.yml` ruft `tests.yml` und `lint.yml` als *reusable workflows* (`uses:`) auf und wartet per **`needs: [tests, lint]`**. Der Deploy-Job startet nur, wenn **beide grün** sind. Deshalb fehlt `main` bewusst in den `push`-Triggern von tests/lint — sonst liefen sie doppelt.

**PHP-Matrix:** nur `8.4`. `composer.json` verlangt `"php": "^8.4"` — **8.3 kann strukturell nicht funktionieren** und schlug deshalb immer fehl (früher fälschlich in der Matrix).

### Deploy-Modell (Server)

- **SSH-User: `deploy`** — niemals `root`, niemals `www-data`. Kein `sudo -u www-data`.
- **Webserver läuft weiter als `www-data`**; der braucht nur Lese-/Ausführrechte auf die App und **Schreibrechte auf `storage/`, `bootstrap/cache/` + `database/`**. Das `database/`-Verzeichnis ist kein Versehen: `SESSION_DRIVER=database` + `CACHE_STORE=database` heißt, **jeder einzelne Request schreibt in die SQLite-Datei** — fehlt hier Schreibrecht, gibt's einen sofortigen HTTP 500 auf jeder Seite (schon einmal passiert). `deploy.yml` erzwingt das bei jedem Lauf per `chmod -R g+w storage bootstrap/cache database` — läuft als `deploy` (Dateibesitzer), kein `sudo` nötig.
- **App-Pfad:** `/var/www/test.dormed24.de`
- Der Deploy führt aus: `git pull --ff-only` → `composer install --no-dev` → `npm ci` → **`npm run build:ssr`** → `optimize:clear` → **`migrate --force`** → `optimize` → **`inertia:stop-ssr`** (SSR-Reload).
- **`migrate --force` spielt nur NEUE Migrationen ein und löscht nie Daten.** Ein **`migrate:fresh --seed` (Sandbox-Reset) passiert ausschließlich manuell auf dem Server** — bewusst nie im Deploy.
- `git pull --ff-only` bricht ab statt zu mergen, falls der Server divergiert (lieber roter Deploy als Merge-Chaos).

### SSR läuft auf dem Server dauerhaft

- Server-`.env`: **`INERTIA_SSR_ENABLED=true`** (der Schalter lebt in der `.env` — die ist **gitignored**, wird also **nie** gepusht. Ein Push schaltet SSR nirgends ein, er liefert nur die Fähigkeit.)
- Der Deploy baut deshalb **`npm run build:ssr`** (Client **+** `bootstrap/ssr/app.js`).
- Ein **systemd-Dienst** (`inertia-ssr-test.service`, `Restart=always`) hält `php artisan inertia:start-ssr` dauerhaft am Laufen.
- **Reload nach dem Deploy:** `php artisan inertia:stop-ssr` fährt den Prozess per HTTP herunter, systemd startet ihn dank `Restart=always` sofort mit dem **neuen Bundle** neu. Kein `sudo` nötig. Ohne diesen Schritt würde SSR nach dem Deploy weiter den **alten** Code ausliefern.
- Die systemd-Unit läuft als **`deploy`**, nicht `www-data`: Node liegt unter `/home/deploy/.local/node-v22/` (Symlink `/usr/local/bin/node`), `www-data` kommt in ein Home mit Modus `750` typischerweise nicht hinein — mit `User=www-data` stirbt der Dienst mit „node not found" in einer Restart-Schleife (schon einmal passiert). Nebeneffekt: least privilege, der SSR-Prozess braucht ohnehin keine Webserver-Rechte.

### GitHub-Konfiguration

**Secret** (Settings → Secrets → Actions) — nur der private Key ist geheim:

| Name | Inhalt |
| --- | --- |
| `DEPLOY_SSH_KEY` | **privater** SSH-Key (der öffentliche liegt in `/home/deploy/.ssh/authorized_keys`) |

**Variables** (Settings → Variables → Actions) — nicht geheim, deshalb bewusst *keine* Secrets:

| Name | Wert |
| --- | --- |
| `DEPLOY_HOST` | `test.dormed24.de` |
| `DEPLOY_USER` | `deploy` |
| `DEPLOY_PATH` | `/var/www/test.dormed24.de` |
| `SSH_KNOWN_HOSTS` | Ausgabe von `ssh-keyscan test.dormed24.de` (öffentlicher Host-Key → verhindert MITM) |

**Kein `DEPLOY_PORT`** — Port 22 ist Default, ein Secret dafür wäre sinnlos.

### Server-Anforderungen (sonst bricht der Deploy)

- **PHP 8.4** (Projekt-Constraint).
- **Node ≥ 20.19** — Vite 8 crasht auf Node 18 (`CustomEvent is not defined`). Node muss im **PATH des `deploy`-Users bei nicht-interaktiver SSH-Session** liegen (nvm in `~/.bashrc` reicht dafür oft **nicht**!). Der Workflow prüft das explizit und bricht mit klarer Meldung ab.
- **Node muss auch für den SSR-Dienst auffindbar sein** — Node **systemweit** installieren bzw. per Symlink in `/usr/local/bin/node` erreichbar machen (nicht ausschließlich per nvm im Home eines einzelnen Users), sonst findet die systemd-Unit ihn nicht.
- **SSR-Dienst muss laufen** (`inertia-ssr-test.service`) — sonst läuft `INERTIA_SSR_ENABLED=true` ins Leere. Siehe SSR-Abschnitt oben.

### ⚠️ `lint.yml` ist derzeit ein weiches Gate

`lint.yml` ruft `npm run format` (`prettier --write`) und `npm run lint` (`eslint --fix`) — **mutierende** Varianten. Es scheitert nur an *nicht* auto-fixbaren Fehlern, nicht an Formatierungs-Drift. Das echte Gate ist damit `tests.yml` (PHPStan + PHPUnit).
**Um es scharf zu machen:** einmal `npm run format` + `vendor/bin/pint` laufen lassen und committen (räumt ~29 Dateien Prettier-Drift + Pint-Drift auf), dann in `lint.yml` auf `composer lint:check`, `npm run lint:check`, `npm run format:check` umstellen. `composer ci:check` bündelt bereits das komplette Gate.

### Sandbox-Reset — kein Skript, nur die nackten Befehle

Kein `update.sh` mehr: `deploy.yml` hält Code/Dependencies/Assets bei jedem Push
aktuell, ein Wrapper-Skript für die restlichen paar Befehle wäre unnötige
Abstraktion für eine seltene, bewusste Handaktion. Der Sandbox-Reset (Katalog auf
sauberen Demo-Stand zurücksetzen) läuft manuell auf dem Server:

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed --force
php artisan optimize
php artisan inertia:stop-ssr   # systemd startet den SSR-Prozess per Restart=always neu
```

**`migrate:fresh --seed` passiert ausschließlich hier, manuell** — `deploy.yml`
macht das nie (nutzt `migrate --force`, das nur neue Migrationen einspielt und
nie Daten löscht).

---

## Noch nicht gebaut

- Wartungsmodus, Produktfilter, Bulk-Aktionen, Bestell-/Kunden-Filter im Admin (siehe `TODO.md`)
- Rating-Throttle/Moderation (ANALYSE-V2 S-C, Wiedervorlage Linus)
- Kleinere Härtungen: Settings-Werte-Validierung, Throttle auf `paypal/order/create` (ANALYSE-V2 S-C)
