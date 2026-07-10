Vollanalyse V4 dormed-shop — vierte Schleife, kompletter 1.0-Readiness-Check
(Arbeitsstand 10.07.2026 — erledigte Punkte werden abgehakt)

Methodik: Phase 1 wurde **unabhängig** vom Code/den Architektur-Docs hergeleitet
(ANALYSE-V1–V3 bewusst nicht gelesen), Phase 2 hat jeden Befund gegen die
Voranalysen klassifiziert: **NEU** / **REGRESSION** / **CARRY-OVER** /
**BEWUSSTE ENTSCHEIDUNG**.

## 📋 Ist-Zustand (Kurzprotokoll, alles selbst ausgeführt)

- **Testsuite:** 210 passed / 2 skipped (Rating-Submission, bewusst — S-1 aus V3),
  875 Assertions, ~4 s. Grün.
- **PHPStan:** Level 7 grün; Baseline **98 Einträge** (V3: 117 → weiter
  geschrumpft, Disziplin hält). Rest ist überwiegend das bekannte Cart-Array/DTO-Thema.
- **ESLint:** 0 Fehler. **svelte-check:** 0 Fehler (Exit 0). Beides mit Node
  20.20.1 via nvm (`engines >=20.19` + `.nvmrc` vorhanden — System-Node ist
  weiterhin 18, ohne nvm crasht Vite 8).
- **Build:** `npm run build` und `npm run build:ssr` grün; SSR-Bundle
  (`bootstrap/ssr/app.js`) baut sauber.
- **composer audit:** 0 Advisories. **npm audit: 8 low** (Kette
  `sveltekit-superforms`/`formsnap` — kam mit den B2-Kit-Peer-Deps aus V3
  ins Projekt; V3 protokollierte noch „npm audit sauber" → leichte
  **REGRESSION**, siehe Q-4).
- **Routen:** 77 App-Routen (`route:list --except-vendor`), Middleware-Zuordnung
  vollständig durchgesehen: Admin komplett hinter `ensure.admin`, Checkout/PayPal
  hinter `auth`+`verified`, Webhook signaturverifiziert + CSRF-ausgenommen +
  `throttle:60,1`, Admin-Login `throttle:6,1`, Fortify-Limiter (login/2FA/passkeys)
  aktiv. Keine ungeschützte Route gefunden; die Rating-Route ist wie dokumentiert
  deaktiviert (auskommentiert, taucht in `route:list` nicht mehr auf).
- **Mass-Assignment:** `User`/`Customer` mit `#[Fillable]`-Allowlist, `is_admin`
  nicht fillable (Fix aus V3 S-5 verifiziert — **TODO.md behauptet noch das
  Gegenteil**, siehe D-2). Settings mit Key-Whitelist + Format-Validierung +
  Verschlüsselung sensibler Keys, Secrets maskiert.
- **Seeder:** `ProductSeeder`-Produktionsguard (`SEED_PRODUCTS_FORCE`) und
  Dev-only User/Customer/Rating-Seeder verifiziert. `DB::prohibitDestructiveCommands()`
  in Produktion aktiv.

**Gesamturteil:** Der Kern (Auth, Cart, Checkout, Settings, Admin) ist auf
gutem 1.0-Niveau — die Voranalysen sind sichtbar abgearbeitet. Es bleiben
**ein neuer Blocker im PayPal-Fehlerpfad**, eine neue Webhook-Idempotenz-Lücke,
das bekannte Deploy-Skript-Thema und SEO-/Doku-Nacharbeiten.

## 🗂 Klassifikations-Übersicht

| Klasse | Punkte |
| --- | --- |
| **NEU** | B1 (Capture-FAILED → Erfolgsseite), S-1 (Webhook kann refunded→paid zurückdrehen), S-2/S-3 (PayPal-Kleinhärtungen), SEO-1 (relative Canonical/og:url im SSR), SEO-3 (Produkt-Cover ungeskaliert), Q-1 (Sort-Duplikat), Q-2 (Bild-Waisen beim Produkt-Löschen), D-1/D-2 (Doku-Drift SCHEMA.md/TODO.md) |
| **REGRESSION** | Q-4 (npm audit 8 low — V3 war sauber; einzige echte Regression, low-severity) |
| **CARRY-OVER** | B2 (update.sh/Deploy — dokumentiert in todo-deploy.md), B-1 bis B-3 (V3 B-3/B-5-Kleinkram), SEO-2 (Hero-WebP), CI-Gate svelte-check, Backup-Strategie, `sessions.user_id`, Cart-DTO |
| **BEWUSST** (nicht re-flaggt) | Kein Bestandskonzept, Cookie-Banner-Attrappe, UI-Kit-Dateien bleiben, synchroner Mailversand, SQLite, Sitemap listet unverfügbare Produkte, Dashboard-Umsatz ohne `is_test`-Filter, Rechnung bleibt `pending` ohne Auto-Cleanup, Zahlung/Versand-Seiten-Überschneidung, ShopHeader-with-hover-Backup, keine atomaren Deploys |

---

## 🔴 Release-Blocker

### B1. Fehlgeschlagener PayPal-Capture endet auf der Erfolgsseite (NEU)

**Kette aus drei Stellen, jede für sich „korrekt", zusammen falsch:**

1. `app/Http/Controllers/PayPalController.php:139` — der FAILED-Zweig von
   `captureOrder()` antwortet mit **HTTP 200** und `{"status": "FAILED"}`
   (Payment FAILED, Order failed). Der Test
   `test_capture_rejects_amount_mismatch` (`tests/Feature/Checkout/PayPalCaptureTest.php:96`)
   zementiert das 200 sogar per `assertOk()`.
2. `resources/js/components/PayPalButton.svelte:110` — `onApprove` prüft nur
   `if (!ok)`. Bei 200+FAILED fällt es durch und ruft
   `router.visit(checkout.success…)` auf.
3. `app/Http/Controllers/CheckoutController.php:138-150` — `success()` prüft
   Auth + Ownership, aber **nicht den Order-Status**. Die failed-Order wird
   gefunden und gerendert.

**Failure-Szenario:** Capture kommt mit Status ≠ COMPLETED zurück — realistisch
z. B. Betragsabweichung (Defense-in-Depth-Zweig), eCheck-Capture mit Status
`PENDING`, oder ein Decline, das srmklive als Fehler-Array statt Exception
liefert (PLAUSIBEL, nicht gegen die Live-API verifiziert). Der Kunde sieht
„Vielen Dank für Ihre Bestellung!" (`Checkout/Success.svelte:64`) inklusive
Bestellzusammenfassung — es gibt aber keine bezahlte Order, keine
Bestätigungsmail, keine Betreiber-Benachrichtigung. Der Warenkorb ist dabei
nicht geleert (kleiner Trost, aber verwirrend obendrauf).

**Fix (klein, ~1–2 h inkl. Tests):**
- `captureOrder()`-FAILED-Zweig auf **422** (o. ä. Nicht-2xx) umstellen und den
  Capture-Status in die Fehlermeldung aufnehmen; den `assertOk()`-Test anpassen.
- Zusätzlich (Defense-in-Depth) in `success()` PayPal-Zweig: Order nur rendern,
  wenn `status` nicht `failed`/`cancelled` ist — sonst Redirect auf
  `checkout.error` mit Hinweis.
- `PayPalButton.onApprove` kann unverändert bleiben (reagiert dann auf `!ok`).

### B2. update.sh ist ein Dev-Reset-Skript, kein Produktions-Deploy (CARRY-OVER, dokumentiert in todo-deploy.md)

`update.sh:37` führt **`php artisan migrate:fresh --seed --force`** aus — droppt
alle Tabellen. In Produktion bricht der Deploy dank
`DB::prohibitDestructiveCommands()` „nur" ab (statt zu wipen), d. h. das Skript
ist auf dem Produktivsystem schlicht unbrauchbar. Außerdem: baut nur
`npm run build` (kein `build:ssr`) und startet den SSR-Prozess nicht neu →
nach jedem Deploy rendert der SSR-Server veralteten Code oder crasht bei
inkompatiblem Manifest.

**Failure-Szenario:** Erster echter Produktions-Deploy schlägt beim
Migrate-Schritt fehl (halb aktualisierter Code-Stand, Caches geleert, kein
Re-Cache) — bzw. mit deaktiviertem Guard wäre die komplette Order-/Kundendatenbank weg.

**Fix (klein, <1 h):** `migrate --force` statt `migrate:fresh --seed --force`;
`npm run build:ssr` statt `build`; `php artisan inertia:stop-ssr || true` +
Neustart des Supervisor-/systemd-Services am Ende; optional `php artisan down/up`
um das Fenster zu schließen. Der Actions-Deploy-Workflow (todo-deploy.md,
Weg A) ist die saubere Fortsetzung, aber unabhängig davon muss das Skript
selbst zuerst entschärft werden.

---

## 🟠 Sicherheitslücken

### S-1. Späte/duplizierte CAPTURE.COMPLETED-Webhooks können `refunded` → `paid` zurückdrehen (NEU)

`app/Support/Orders/OrderManager.php:126-129` — `transitionToPaid()` schützt
nur gegen „bereits paid":

```php
->where('status', '!=', 'paid')
->update(['status' => 'paid']);
```

`cancelled`, `failed` und **`refunded`** sind gültige Ausgangszustände für den
Übergang. Gleichzeitig setzt `PayPalController::handleCaptureCompleted()`
(`app/Http/Controllers/PayPalController.php:337-344`) Payment + Order ohne
jede Statusprüfung auf COMPLETED/paid und überschreibt dabei auch
`response_data` (Audit-Trail des Refunds geht verloren).

**Failure-Szenario:** Order bezahlt → Admin-Refund (oder Refund im
PayPal-Dashboard) → Order `refunded`, Payment `REFUNDED`. PayPal-Webhooks sind
at-least-once und nicht geordnet; ein verspätetes oder erneut zugestelltes
`PAYMENT.CAPTURE.COMPLETED` (Retry bis zu 3 Tage) matcht per `paypal_capture_id`,
setzt Payment zurück auf COMPLETED, `markPaid()` dreht die Order auf `paid`
und **verschickt beide Bestätigungsmails erneut** — Buchhaltungsstand falsch,
Kunde verwirrt.

**Fix (klein, ~1 h inkl. Test):** `transitionToPaid()` auf
`->where('status', 'pending')` einschränken (deckt auch den Fall ab, dass eine
per `cancelStalePendingPayPalOrders()` stornierte Order durch einen alten
Approval-Tab via `afterPayment` wieder `paid` würde); in
`handleCaptureCompleted()` Payments mit Status `REFUNDED` überspringen (Log
statt Update). Test: REFUNDED-Payment + COMPLETED-Webhook → Status bleibt,
keine Mails (`Mail::fake`).

### S-2. `afterPayment` captured fremde Orders und leert den falschen Warenkorb (NEU, klein)

`app/Http/Controllers/PayPalController.php:204-243` — die Route ist seit V3
hinter `auth` (bewusste Entscheidung: Ownership prüft erst die Success-Seite).
Neue Nuance aus dieser Analyse: der Handler sucht das Payment **ohne
Customer-Scope** und ruft bei Erfolg `completeCapture()` auf — das markiert die
**fremde** Order als paid und leert den **eigenen** Session-Cart des Aufrufers
(`PayPalController.php:285`). Ein eingeloggter Dritter mit bekanntem Token
kann so fremde approved Orders capturen (Geld fließt an den Shop — Schaden
gering, aber der Cart-Clear trifft die falsche Session, und die Mail geht raus,
bevor der legitime Käufer selbst captured).

**Fix (eine Zeile + Test):** denselben `whereHas('order', customer_id)`-Scope
wie in `captureOrder()` verwenden; ohne Match Redirect auf `checkout.index`.

### S-3. Webhook-Capture ohne Betragsabgleich (NEU, klein)

`handleCaptureCompleted()` markiert paid ohne den Betrag gegen die Order zu
prüfen — der JS-/Return-Pfad tut das (via `completeCapture()`), der
Webhook-Pfad nicht. Da der Webhook signaturverifiziert ist und die Capture aus
der eigenen Order stammt, ist das Risiko gering — aber die Prüfung existiert
schon und sollte für alle drei Pfade gelten. (Mit S-1 zusammen fixbar.)

### S-4. Kleinkram (NEU)

- `SESSION_SECURE_COOKIE` fehlt in `.env.example` — die Launch-Checkliste
  verlangt es, die Vorlage kennt den Key nicht. Eine auskommentierte Zeile
  mit Kommentar spart den Produktions-Stolperer.
- `app/Support/Address/AddressRules.php:22` — `country` ist nur `size:2`,
  keine ISO-3166-Validierung. Ein Fantasie-Code wandert bis in den
  PayPal-`createOrder`-Call (`country_code`) und produziert dort einen
  500er statt einer Feldmeldung. `Rule::in([...])` oder zumindest
  `alpha`+`uppercase`.
- `routes/settings.php:19-21` — `profile.destroy` steht in der
  `verified`-Gruppe: ein Kunde mit **unbestätigter** E-Mail kann sein Konto
  nicht selbst löschen (DSGVO-Reibung; Art. 17 kennt keine
  Verifikations-Vorbedingung). `destroy` in die nur-`auth`-Gruppe ziehen.

---

## 🟡 Businesslogik

### B-1. Betragsabweichung beim Capture: Geld eingezogen, niemand merkt es (CARRY-OVER V3 B-3, durch B1 verschärft)

Unverändert offen — und durch B1 in der Wirkung schlimmer als in V3
beschrieben: Bei Abweichung ist das Geld bei PayPal eingezogen, Order/Payment
stehen auf failed, es gibt nur ein `Log::error` — **und der Kunde sieht die
Erfolgsseite** (B1-Kette). Minimal-Fix bleibt: diesen Fall zusätzlich als Mail
an `shop.email` melden. Mit dem B1-Fix zeigt der Kunde-Pfad dann wenigstens
einen Fehler.

### B-2. `confirmPayment` ohne `notify` umgeht den atomaren Übergang (CARRY-OVER V3 B-5)

`app/Http/Controllers/Admin/OrderController.php:103` — direktes
`$order->update(['status' => 'paid'])` statt `transitionToPaid()`. Harmlos
(Precondition-Check davor), aber der atomare Pfad existiert genau dafür.
Wird mit dem S-1-Fix (`pending`-only) relevanter: einheitlich machen.

### B-3. `summaryFromOrder`: `product_number`-Fallback auf OrderItem-ID (CARRY-OVER V3 B-5)

`app/Support/Orders/OrderManager.php:195` — bei gelöschtem Produkt steht auf
Success-Seite/Mails eine Nummer, die nie eine Produktnummer war. `?? '—'`.

### B-4. Doppel-Submit auf `checkout.submit` kann zwei Rechnungs-Orders erzeugen (NEU, klein)

`CheckoutController::submit()` hat keine Idempotenz — zwei parallele Requests
lesen beide einen nicht-leeren Cart und legen zwei Orders an (das
Cart-Leeren am Ende schützt nur sequenziell). Der Hold-to-confirm-Button macht
das praktisch unwahrscheinlich; wenn gewünscht: `session()->token()`-basierter
Idempotency-Key oder `Cache::lock` um den Submit. Kein 1.0-Blocker.

---

## 🔵 Simplifizierung & Code-Refinement

### Q-1. Sortier-Logik dupliziert (NEU)

`ProductController::index()` (`app/Http/Controllers/ProductController.php:35-41`)
und `CategoryController::show()` (`app/Http/Controllers/CategoryController.php:16-22`)
enthalten dasselbe `match ($sort)` Zeile für Zeile. Ein kleiner
`ProductSort::fromRequest()`-Helper (oder Enum) macht beide Controller kürzer
und hält künftige Sortieroptionen konsistent. ~30 min.

### Q-2. Produkt-Löschen hinterlässt Bilddateien auf der Disk (NEU)

`Admin\ProductController::destroy()` löscht das Produkt; die
`product_images`-Zeilen kaskadieren in der DB, aber die Dateien unter
`storage/app/public/products/{id}/` bleiben liegen (nur der Einzelbild-Delete
in `ProductImageController::destroy()` räumt Dateien auf). Bei 45 MB
Produktbildern aktuell kein Drama, aber es akkumuliert. Fix:
`Storage::disk('public')->deleteDirectory("products/{$product->id}")` im
`destroy()` (bewusst dort statt Model-Event — Seeder nutzt
`WithoutModelEvents`). ~20 min inkl. Test.

### Q-3. Carry-over-Rest (unverändert, klein)

- `sessions.user_id` kosmetisch (V2 S6).
- Cart-Array → DTO (PHPStan-Baseline-Rest, V1 #18) — Baseline ist mit 98
  Einträgen weiter auf gutem Schrumpfkurs (V2: ~180, V3: 117).

### Q-4. npm audit: 8 low-Advisories über die UI-Kit-Peer-Deps (REGRESSION, klein)

V3 protokollierte „npm audit sauber"; die beim B2-Fix installierten
Kit-Peer-Deps (`sveltekit-superforms` → `formsnap`) ziehen jetzt 8
low-severity-Advisories nach sich. Beide Pakete werden von keinem aktiven
Code importiert (nur von den bewusst behaltenen, ungenutzten Kit-Komponenten).
Optionen: `npm audit fix` versuchen, Versionen pinnen, oder akzeptieren und
im nächsten Kit-Update mitnehmen. Kein Risiko im ausgelieferten Bundle
(tree-shaken), aber der „audit sauber"-Anspruch aus V2/V3 ist damit formal
gebrochen.

### D-1. SCHEMA.md ist der DB weit hinterher (NEU — Doku-Drift mit Selbstverpflichtung)

`database/SCHEMA.md` erklärt sich selbst zur „einzigen Quelle der Wahrheit"
mit Wartungspflicht — tatsächlich fehlen/stimmen nicht:

- Tabelle **`product_variants` fehlt komplett** (Übersicht, Detail, FK-Liste),
  dabei ist das Varianten-Preismodell seit V2 zentral.
- FK-Übersicht: `orders.customer_id` steht auf **CASCADE** — ist seit
  Migration `2026_07_07_175921` `SET NULL` (der GoBD-Fix S-A; im Fließtext
  korrekt, in der Tabelle falsch).
- `products`: `manufacturer_id` ist seit `2026_07_02_212025` **nullable**
  (Doku: NOT NULL), `is_available` (Migration `2026_06_30_000001`) fehlt.
- `orders.status`-Liste enthält noch `processing`/`completed` — beide wurden
  bewusst entfernt (README dokumentiert das korrekt).

~30 min Abgleich gegen `database-schema`; bei der Gelegenheit `payments`- und
`product_variants`-Spalten aus den Migrationen übernehmen.

### D-2. TODO.md widerspricht dem Code (NEU — Doku-Drift)

`TODO.md:29-32` führt „🔴 is_admin aus User Fillable entfernen" als offen —
das ist seit V3 S-5 erledigt (`app/Models/User.php:17`, `#[Fillable]` ohne
`is_admin`, inkl. Tests). Den Punkt abhaken/streichen, sonst frisst der
nächste Audit-Durchlauf wieder Zeit damit. (Der Rating-Punkt in TODO.md ist
dagegen korrekt als Wiedervorlage markiert.)

---

## 🔍 SEO

Grundgerüst weiterhin gut (AppHead überall, Product-/FAQ-/Organization-JSON-LD
mit `<`-Escaping, Sitemap mit Cache, robots.txt mit Sitemap-Zeile, SSR
aktivierbar und Bundle baut). Konkrete Lücken:

### SEO-1. SSR liefert relative Canonical- und og:url-Tags (NEU)

`resources/js/components/AppHead.svelte:30-32`:

```ts
return typeof window === 'undefined'
    ? canonical                    // ← SSR: bleibt "/products/5"
    : new URL(canonical, window.location.origin).href;
```

Genau die Konsumenten, für die SSR gebaut wurde (Link-Preview-Bots, Crawler
ohne JS), bekommen `<link rel="canonical" href="/products/5">` und
`<meta property="og:url" content="/products/5">`. Open Graph verlangt absolute
URLs — WhatsApp/Slack/LinkedIn ignorieren relative `og:url` bzw. raten. Der
Client „repariert" das nach Hydration, aber das sieht kein Bot.
**Fix (~30 min):** absolute Basis in den SSR-Pfad geben — z. B. `app.url` als
Shared Prop (`config('app.url')`) und in `AppHead` `new URL(canonical, base)`
für beide Zweige; `og:image` ist schon absolut (Storage-URL). Verifikation wie
in V3: `curl` gegen die SSR-Antwort.

### SEO-2. Hero-Bild weiterhin 731-KB-PNG (CARRY-OVER V3 SEO-5, Restpunkt)

Attribute (width/height, `fetchpriority="high"`, `decoding="async"`) sind da —
die WebP/AVIF-Konvertierung ist der bewusst offene Rest. ~80–150 KB statt
731 KB, größter Einzelhebel für den Startseiten-LCP. `cwebp`/`avifenc`
Einmal-Job, 15 min.

### SEO-3. Produkt-Cover werden unskaliert ausgeliefert (NEU)

`storage/app/public/products/` liegt bei **45 MB**, etliche Cover >300 KB
(seeded aus Shopware-Media-URLs in Originalgröße). Die Listing-Seiten
(`/products`, Kategorien, Startseite) rendern diese Originale als
Karten-Thumbnails — 24 Karten × ein paar hundert KB pro Seite drücken LCP und
Datenvolumen mobil massiv. Optionen nach Aufwand: (a) Einmal-Skript, das
bestehende Cover auf max. ~800 px WebP nachverdichtet + Resize beim
Admin-Upload (`intervention/image`, Abhängigkeits-OK nötig), (b) volle
Thumbnail-Pipeline mit `srcset`. Für 1.0 reicht (a). ~2–4 h.

### SEO-Notizen (bewusst, nicht re-flaggt)

Sitemap listet unverfügbare Produkte (Entscheidung V2), Zahlung/Versand-Seiten
überschneiden sich thematisch (auf Wunsch unberührt), `robots.txt` hardcodet
die Prod-Domain (für die eine Umgebung okay).

---

## 🚀 Launch-Checkliste (Delta zu README/V3 — dort Punkte 1–7 unverändert gültig)

1. **B2 zuerst:** `update.sh` produktionsfest (migrate --force, build:ssr,
   SSR-Neustart) — vor dem ersten echten Deploy, unabhängig von der
   CI/CD-Entscheidung aus todo-deploy.md (Weg A: Actions → SSH).
2. **SSR-Betrieb:** `inertia:start-ssr` als Supervisor/systemd-Dauerprozess +
   `INERTIA_SSR_ENABLED=true`; nach SEO-1-Fix einmal per `curl` die absolute
   Canonical/og:url in der rohen HTML-Antwort verifizieren.
3. **`SESSION_SECURE_COOKIE=true`** setzen (und in `.env.example` dokumentieren, S-4).
4. **CI-Gate nachziehen (Carry-over V3):** `tests.yml` fährt PHPStan + Tests +
   Build, aber **kein `svelte-check`** (`composer types:check` = PHPStan;
   `npm run types:check` = svelte-check — Namenskollision beachten). Und
   `lint.yml` ruft `npm run format`/`npm run lint` (write/fix-Varianten) auf —
   Formatverstöße failen den Build nicht. Auf `format:check`/`lint:check` +
   `npm run types:check` umstellen, sonst driftet das in V3 mühsam
   grüngemachte Gate wieder.
5. **DB-Backup-Strategie** (SQLite-Datei) — dritter Carry-over in Folge,
   vor Launch entscheiden (simpelster Start: nächtliches
   `sqlite3 .backup`-Cron + Offsite-Kopie, zusammen mit `storage/app/public`).
6. PayPal-Webhook-ID im Admin pflegen (sonst werden alle Webhooks abgelehnt —
   nur im Log sichtbar) — unverändert aus V3.

## 🧪 Testing — fehlende Abdeckung

- **B1:** Capture-FAILED → Nicht-2xx + Success-Seite verweigert failed-Orders
  (bestehenden `assertOk`-Test anpassen, neuen Success-Status-Test ergänzen).
- **S-1:** REFUNDED-Payment + verspäteter CAPTURE.COMPLETED-Webhook → Status
  bleibt `refunded`, keine Mails (`Mail::fake`).
- **S-2:** `afterPayment` mit fremdem Token → kein Capture, Redirect.
- **Manueller PayPal-Sandbox-Browsertest** — Carry-over seit V2 (CSRF in
  PHPUnit deaktiviert, Button-Flow serverseitig nicht testbar). Nach dem
  B1-Fix Pflicht, weil der Fehlerpfad-UX sich ändert.
- Nach Q-2: Test „Produkt-Löschen räumt Bildverzeichnis".

## Empfohlene Reihenfolge

1. **B1** (Capture-FAILED-Kette: 422 + Success-Status-Guard) — kundensichtbarer
   Geldweg, mit Tests.
2. **S-1 + S-3 + B-2 zusammen** (transitionToPaid nur `pending`→`paid`,
   Webhook-Guard + Betragsabgleich, confirmPayment vereinheitlichen) — ein
   zusammenhängender OrderManager/Webhook-Commit mit Tests.
3. **B2** (update.sh entschärfen) → danach Deploy-Workflow-Entscheidung
   (todo-deploy.md Weg A) + CI-Gate-Fix (Launch-Punkt 4).
4. **S-2** (afterPayment-Ownership, eine Zeile) + **S-4-Kleinkram**.
5. **SEO-1** (absolute Canonical im SSR) → **SEO-2/SEO-3** (Bilder) — vor dem
   ersten Indexieren billiger als danach.
6. **D-1/D-2** (SCHEMA.md, TODO.md nachziehen) + Q-1/Q-2 nach Gelegenheit.
