# dormed-shop — Agent Context

Medizintechnik-Shop dormed 24 (Ablösung für Shopware): Laravel 13, Inertia v3, Svelte 5. Antworten auf Deutsch.

## Wohin für was

Das Dokument zum Thema vor der Änderung lesen, danach nachziehen.

| Wenn du … | dann lies |
| --- | --- |
| eine Migration oder ein Model änderst | `database/SCHEMA.md` (jede Schemaänderung dort mitpflegen) |
| einen Flow änderst (Warenkorb, Checkout, PayPal, Verifikation, Varianten) | `README.md`, Kern-Flows (Änderung dort mitpflegen) |
| einen Fachbegriff brauchst oder benennst | `GLOSSARY.md` |
| eine Architekturfrage entscheidest | `.docs/adr/` (Namespaces, kein Fulfillment-Status, später Capture, keine Admin-Konfiguration); Umbauplan `.todo/architektur-plan.md` |
| Workflows, Deploy, Server, SSR oder Sandbox-Reset anfasst | `.docs/deployment.md` |
| Offenes suchst | `TODO.md`, `ANALYSE.md` |

## Prüfen vor dem Push

PHP läuft nur in der CI (PR gegen `main` öffnen, Ergebnis lesen). Lokal laufen Prettier, ESLint und `svelte-check` (`npm run types:check`) auch nach dem Schreiben von Tests, weil Testdateien mitgeprüft werden. Vitest braucht die generierten Wayfinder-Dateien (`@/actions`, `@/routes`, gitignored) und scheitert ohne sie an fehlenden Modulen. Ein PR wird erst nach grüner CI gemergt: der Deploy auf `main` hängt an denselben Gates.

## Bereiche und Auth

Zwei getrennte Bereiche mit zwei Guards:

| Guard | Model | Login | Middleware |
| --- | --- | --- | --- |
| `web` | `Customer` (Tabelle `customers`, Fortify, Passkeys, 2FA, `MustVerifyEmail`) | `/login` | `auth` |
| `admin` | `EnvAdmin` (aus `ADMIN_EMAIL`/`ADMIN_PASSWORD`, bcrypt-Hash; keine Tabelle, kein Fortify) | `/admin/login` | `ensure.admin` |

- Bestellen setzt eine bestätigte E-Mail voraus: `verified` auf `checkout.confirm/address/submit` und `paypal.order.create/capture`.
- In Svelte ist `page.props.auth.user` der aktuelle `Customer` (Typ aus `@/types`).
- Layouts löst `resources/js/app.ts` nach Seitenmuster auf: `Admin/*` → `AppLayout`, `Admin/Login` und `auth/*` → `AuthLayout`, `settings/*` → `CustomerLayout` + `SettingsLayout`. Alle Shop-Seiten sind standalone und binden `<ShopHeader>` und `<AppFooter>` selbst ein.

## Shop-URLs

Die vier Kategorien aus `config/categories.php` sind die Einstiegsseiten; es gibt keine „Alle Produkte“-Seite.

- Kategorie: `/{kategorie}`. Produkt: `/{kategorie}/{produkt-slug}`. Suche: `/search` (JSON für den Header-Dropdown, sonst Ergebnisseite mit `noindex`).
- Der Slug entsteht einmal beim Anlegen aus dem Namen (Kleinbuchstaben, Umlaute ausgeschrieben, Bindestriche), ist global eindeutig und ändert sich nie; der Produktname ist nach dem Anlegen unveränderlich. `Product::url()` liefert die URL, ohne Kategorie oder Slug `null`. Links über `Product::url()` oder Wayfinder (`ProductController.show.url({category, product})`) bauen.
- Die Kategorie ist Pflicht; `Product::available()` liefert nur Produkte mit Kategorie. Alte `/products*`-URLs gibt es nicht (404, keine Weiterleitung).
- `routes/categories.php` (Catch-all auf ein und zwei Segmente) steht in `routes/web.php` zuletzt, sonst verschluckt es feste Seiten.
- Rating-POSTs bleiben unter `/products/{id}/ratings` (`routes/public/rating.php`).
- Sitemap: Startseite, Info- und Unternehmensseiten, Kategorien, Produkte mit Kategorie.

## Warenkorb

`App\Support\Cart\CartService` liest und schreibt den Warenkorb über den `CartStore`-Contract; `CartService::cart()` liefert das `Cart`-DTO (Positionen, Versandarten, Adressen, Summen), geteilt als Prop `cart`.

- Session-State: `items[lineKey => quantity]`. Line-Key = `productId` oder `productId:variantId`; jede Variante ist eine eigene Zeile, das Frontend adressiert sie über `item.line_key` und `/cart/items/{product}/{variant?}`.
- Preise und Namen kommen bei jedem Aufruf live aus der DB; der Snapshot entsteht erst in `OrderManager::createFromCart`.
- Varianten haben einen absoluten Endpreis (`product_variants.price`); Produkte mit Varianten sind nur als Variante bestellbar, das Label wird Teil des Zeilennamens.
- Die Zahlart gehört nicht zum Warenkorb: der Klick auf einen Zahlungs-Button bestimmt sie.

## Bilder

Produktbilder liegen ausschließlich als **WebP und AVIF** im public-Disk: gleicher Name, nur die Endung unterscheidet sich (`products/7/abc.webp` + `abc.avif`). Das Upload-Format ist egal (JPEG, PNG, GIF, BMP, WebP, AVIF); das Original wird nicht aufbewahrt.

- **Alles läuft über `App\Support\Images\ImageStore`** (`store`, `convertExisting`, `delete`); `ImageConverter` (GD) wandelt um. Kein `->store()`/`Storage::put()` für Produktbilder daneben, sonst fehlt das zweite Format.
- `product_images.path` zeigt auf die WebP-Datei, `width`/`height` sind die Maße des gespeicherten Bildes. `ProductImage::url` = WebP (Fallback-`src`, `og:image`), `avif_url` = AVIF.
- Das Konvertieren läuft synchron im Upload-Request (Bild wird auf 2400 px Kantenlänge begrenzt, über 24 Megapixel wird abgelehnt). Der Server braucht PHP `gd` mit WebP und AVIF sowie `exif` (`Dockerfile.prod`, CI).
- **Im Frontend jedes Produktbild über `components/Picture.svelte`** (`<picture>` mit AVIF- und WebP-`source`, `<img>` mit `width`/`height`, `loading="lazy"`, `decoding="async"`); kein nacktes `<img src={…url}>`. `src` ist die WebP-URL, die AVIF-URL leitet `lib/picture.ts` daraus ab. Das Hauptbild der Produktseite lädt mit `loading="eager"`.
- Bestandsbilder (JPEG/PNG) umwandeln: `php artisan product-images:convert` (idempotent, `--dry-run`). Bis dahin liefert `Picture` solche Bilder als schlichtes `<img>` ohne `source` aus.
- Newsletter-Bilder (`Admin/NewsletterController`) bleiben bewusst JPEG/PNG: E-Mail-Clients (Outlook) können WebP/AVIF nicht zuverlässig darstellen.

## Layout-Varianten

Zum Ausprobieren mehrerer Designs einer Seite gilt das Schema aus den Admin-Masken und dem Checkout (`lib/layoutVariants.ts`, `lib/useLayoutVariant.svelte.ts`, `components/VariantSwitcher.svelte`):

- Die Seite (Container) hält Zustand und Aktionen und gibt Bausteine als Snippets weiter; die Layout-Komponenten ordnen sie nur an und platzieren jeden Baustein genau einmal.
- Optionen stehen in `lib/<seite>.ts` (`key`, `label`; die erste Option ist der Standard und das heutige Layout). Die Auswahl steht in `?variant=B`.
- Pro Layout ein Test „jeder Baustein genau einmal“, pro Seite ein Rendertest je Variante.
- Auf öffentlichen Seiten sieht jeder Besucher die Pille. Nach der Entscheidung bleibt eine Variante, die übrigen Layouts und die Pille entfallen.

## Frontend

### Svelte 5 Runes

`$props()`, `$state()`, `$derived()`, `$effect()`, `onclick=`, `{#snippet}` + `{@render}`.

### shadcn-svelte

- `gap-*` statt `space-*`; `size-*` bei gleicher Breite und Höhe; `cn()` aus `@/lib/utils` für bedingte Klassen.
- `Sheet`, `Dialog`, `Drawer` bekommen immer einen `Title` (ggf. `class="sr-only"`); Overlays erhalten keine manuellen `z-index`-Werte.
- Farben über Theme-Tokens (`primary`, `brand-accent`, `brand-secondary`), keine Hex-Werte.

### Navigation und Routen

- Interne Links über `<Link>` aus `@inertiajs/svelte`; `<a>` nur für externe Ziele und `tel:`, `mailto:`, `#anker`.
- Routen über Wayfinder: Controller `@/actions/App/Http/Controllers/…`, benannte Routen `@/routes`, URL über `toUrl()` aus `@/lib/utils`.
- **`php artisan wayfinder:generate` nur mit `--with-form`.** `vite.config.ts` generiert mit `.form()`-Helpern (die Fortify-Auth-Seiten nutzen `store.form()`); ein Aufruf ohne das Flag überschreibt sie, und die Seite bleibt clientseitig leer (`store.form is not a function`, nur in der Browser-Konsole).
- Rohe `fetch()`-Aufrufe laufen über `fetchJson()` aus `@/lib/http` (CSRF; sonst 419). `router.*` sendet das XSRF-Token selbst.

### Muster

Button als Link (`asChild`):

```svelte
<Button asChild class="...">
    {#snippet children(props)}
        <Link href="/pfad" class={props.class}>Label</Link>
    {/snippet}
</Button>
```

Sheet schließen und navigieren: `<SheetClose asChild>` mit `closeProps.onClick as () => void` am `Link`. `SheetTrigger asChild` reicht `props.onclick` (klein) weiter.

### Admin-Listen und Status

- Neue Index-Seiten beschreiben Spalten, Suchfelder und Texte und nutzen `AdminDataTable`, `RowActions` und `StatusBadge`; keine eigene tanstack-Verdrahtung, keine kopierten Badges.
- Status-Labels und -Töne kommen aus PHP: Enums nutzen `App\Support\HasStatusBadge` (`label()`, `tone()`) und gehen per `Enum::badges()` als Prop (`statuses`) an die Seite; Bestellstatus als geteilte Prop `orderStatuses` (`lib/status.ts`). Töne: `neutral`, `info`, `success`, `warning`, `danger`.

### PayPal-SDK

`components/PayPalButtons.svelte` rendert pro Zahlart einen SDK-Button (`fundingSource`). Das SDK prüft Werte streng: `style.height` ist eine Zahl (25–55); ein falscher Typ wirft beim Rendern, und es erscheint kein Button. Sperren geht über `actions.disable()`/`actions.enable()` (`onInit`), nicht über ein Attribut. Änderungen an der Konfiguration brauchen einen Test in `PayPalButtons.test.ts`.

## Zahlungen

Vier Bezahlarten (`PaymentMethod`): Rechnung (`invoice`) sowie PayPal, Lastschrift und Kreditkarte (`paypal`, `sepa`, `card`; alle drei laufen über PayPal und kommen als `funding_source` an `paypal.order.create`). Auf `Checkout/Confirm` steht je Zahlart ein eigener Button; Rechnung ist ein normaler Klick (`CheckoutController::submit`). Alle teilen eine Bedingung (AGB, Adresse, nicht leerer Warenkorb) und werden gemeinsam gesperrt. Bezahlarten, Status und Zahlungsstatus sind Enums (`PaymentMethod`, `OrderStatus`, `PaymentStatus`), keine Strings; Labels über `PaymentMethod::label()`.

| Bezahlart | Flow | Status nach Abschluss |
| --- | --- | --- |
| Rechnung | `CheckoutController::submit` | `pending` (Zahlung per Überweisung) |
| PayPal, Lastschrift, Kreditkarte | `PayPalController` (SDK createOrder/captureOrder) und Return-URL `paypal/after-payment` → `PaymentReconciliation` | `paid` nach Capture |

Regeln:

- **`App\Support\Orders\OrderManager` ist die einzige Stelle**, die aus dem Cart eine Order baut (`createFromCart`, transaktional) und den Bestellstatus ändert (`transitionTo` atomar, `markPaid`, `confirmInvoicePayment`, `cancelAbandonedPayPalOrders` für alle PayPal-Zahlarten). Erlaubte Übergänge: `OrderStatus::reachableFrom()`. Kein `$order->update(['status' => …])` außerhalb.
- Neue Gateways docken über einen Adapter für `App\Support\Orders\Gateway\PaymentGateway` an; Controller bleiben frei von Gateway-Logik.
- **Zwei getrennte Mail-Pfade:**
  - `sendConfirmations()` / `markPaid()` → `OrderConfirmationMail` (Kunde, mit Bankdaten falls unbezahlt) und `NewOrderMail` (Betreiber). Rechnung direkt bei `submit()`, PayPal einmalig bei `markPaid()`.
  - `confirmInvoicePayment()` (Admin-Button) → eigenes `PaymentConfirmationMail`, nur an den Kunden, eigenes Template. Kein `if/else` in einem gemeinsamen Template.
- **PayPal in drei Schichten:**
  - `PayPalService` spricht über das SDK mit der API und gibt rohes JSON zurück.
  - `PayPalGateway` übersetzt es in `CreatedPayment`, `CaptureResult`, `RefundResult`, `WebhookEvent`; nur hier wird PayPal-JSON gelesen, hier wird nichts gespeichert.
  - `PaymentReconciliation` prüft Betrag und Währung, setzt den Payment-Status, mailt bei Abweichung und löst Bestell-Übergänge über den `OrderManager` aus. JS-Capture, Return-URL, Webhooks und Admin-Erstattung laufen alle hier durch.
  - `PayPalController` macht nur HTTP (Eigentum prüfen, Antwort bauen, Warenkorb leeren). Tests für Abgleich-Regeln nutzen `Tests\Support\FakePaymentGateway`, Tests für PayPal-JSON die Fixtures (`PayPalGatewayTest`).
- **Webhooks** (`/paypal/webhook`, signaturverifiziert): `CAPTURE.COMPLETED` → `markPaid`; `REFUNDED`/`DENIED`/`REVERSED` setzen Payment und Order. Beim Refund ist `resource.id` die Refund-ID, die Capture-ID steckt im `up`-Link (löst `PayPalGateway`).
- **Modus:** `App\Support\PaymentMode` — live nur bei `APP_ENV=production` und `APP_DEBUG=false`, sonst sandbox; kein Admin-Override. Zugangsdaten nur aus der Env (`PAYPAL_SANDBOX_*`/`PAYPAL_LIVE_*`, `MAIL_SANDBOX_*`/`MAIL_LIVE_*`, angewendet durch `ModeMailConfiguration`).
- **Keine Konfiguration im Admin** (`.docs/adr/0004`): Kontakt und Bankverbindung stehen in `config/shop.php` und werden nur über `App\Support\ShopContact` gelesen; Shopname = `APP_NAME`. Empfänger der Betreibermails ist `config('shop.contact.email')`; beide Bestellmails laufen synchron im Request.

### Betriebsmodell

Der Shop deckt Bestellannahme und Zahlungsabwicklung ab; Rechnungsstellung, DPD-Versand und Buchhaltung laufen bewusst außerhalb im Rechnungswesen.

- PayPal-Bestellungen steuert allein der Capture-Flow bzw. Webhook (`PayPalController` → `PaymentReconciliation` → `OrderManager`); der Admin ändert sie nicht. Ein verspäteter Capture macht eine `failed`/`cancelled`-Bestellung bezahlt, `refunded` ist endgültig (`.docs/adr/0003`). Es gibt kein Status-Dropdown im Admin.
- Rechnungsbestellungen: einzige manuelle Aktion ist „Zahlungseingang bestätigen“ (`orders.confirm-payment`, `pending` → `paid`; nur bei `payment_method === 'invoice'` und Status `pending`, sonst 422). Zusätzlich gibt es PayPal-Refund (`orders.refund`).
- Es gibt keinen Fulfillment-Status (`.docs/adr/0002`); `paid` ist der finale Erfolgszustand. Storno über den Admin ist nicht vorgesehen.

## Entscheidung Linus

`ShopHeader-with-hover.svelte` (Mega-Menü-Variante) sollte laut Entscheidung 07/2026 erhalten bleiben. PR #40 hat die Datei gelöscht (sie referenzierte die entfernte Route `ProductController.index`); ob sie wiederhergestellt wird, ist offen.
