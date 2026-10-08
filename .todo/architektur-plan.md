# Plan: Architektur vertiefen, dann nach Fachgebieten ordnen

> Status: **Entwurf, nicht umgesetzt.** Grundlage ist das Architektur-Review vom 08.10.2026 (Stand `main` nach PR #28 plus PR #29, Social-Media und Magazin).

## Ziel

Die Codebase soll mit den neuen Gebieten (Marketing: Newsletter, Social-Media, Magazin; später Versand und Veröffentlichen) wachsen können, ohne dass jede Erweiterung an vier Stellen gleichzeitig ansetzt.

Der Weg dahin:

1. **Erst vertiefen:** wenige Module mit kleinem Interface, hinter dem viel Verhalten steckt.
2. **Dann ordnen:** diese Module bekommen Namespaces nach Fachgebiet (Core, Shop, Orders, Marketing).

Reines Umziehen von Dateien ist kein Ziel. Es verschiebt Komplexität, statt sie zu bündeln.

## Begriffe

Die Begriffe stammen aus `.claude/skills/codebase-design`:

| Begriff | Bedeutung |
|---|---|
| **Modul** | alles mit Interface und Implementation, von der Funktion bis zum Fachgebiet |
| **Interface** | alles, was ein Aufrufer wissen muss: Methoden, Regeln, Fehlerfälle, Reihenfolge |
| **tief / flach** | viel Verhalten hinter kleinem Interface (tief), Interface fast so groß wie die Implementation (flach) |
| **Seam** | die Stelle, an der ein Interface liegt und an der man Verhalten austauschen kann |
| **Adapter** | konkrete Umsetzung an einem Seam (z. B. PayPal live, Fake im Test) |
| **Locality** | Änderungen, Fehler und Tests konzentrieren sich an einer Stelle |
| **Leverage** | ein Interface, viele Aufrufer |

## Ausgangslage (Befunde aus dem Review)

| Thema | Heute | Folge |
|---|---|---|
| Größe | ca. 7.300 Zeilen PHP in `app/`, ca. 20.400 Zeilen Svelte, 57 Testdateien | trägt, kein Modul-Framework nötig |
| Ordnung | nach Technik (`Http/Controllers`, `Models`, `Support/*`, `Services`) | Fachgebiete nur in `Support/*` erkennbar; „Support vs. Services“ ohne Regel |
| Tiefe Module | `CartService` hinter `CartStore` (Session-Adapter), `ProductFilters`, `HtmlSanitizer` | Vorbild für den Rest |
| Bestellstatus | Status wird gesetzt in `PayPalController` (736 Zeilen), `Admin/OrderController`, `OrderManager`; gelesen in `DashboardController`, `Customer`, `CustomerOrderController`, `CheckoutController` | Regel „paid/refunded sind Endzustände“ steht 4×; `setStatusByCaptureId`: „Ob der Übergang erlaubt ist, prüfen die Aufrufer.“ |
| Zahlungsstatus | `CREATED/APPROVED/PENDING/COMPLETED/FAILED/MISMATCH/REFUNDED/REVERSED` nur als Strings, auch im Frontend (`Admin/Orders/Show.svelte`) | Tippfehler unsichtbar |
| PayPal | JSON wird im Controller und im Service geparst; `PayPalService::recordPayment` schreibt das Payment-Model | Adapter kennt Persistenz; Logik nur über HTTP-Tests erreichbar |
| AGENTS.md | „`OrderManager` ist die einzige Stelle … Neue Gateways hier andocken, nicht in den Controllern duplizieren“ | stimmt heute nicht mehr |
| Admin-Listen | 7 `Index.svelte` mit je 245–295 Zeilen fast gleicher tanstack-Verdrahtung, 3 kopierte und 1 gemeinsame `StatusBadge.svelte`, 5 kopierte `*Actions.svelte` | jeder neue Bereich kopiert ca. 250 Zeilen |
| Einstellungen | `Setting::get('…')` an 9 Stellen, Schlüssel als Strings, erlaubte Schlüssel privat in `Admin/SettingController`; zwei verschiedene Standard-Telefonnummern | widersprüchliche Defaults |
| Queue/Events | kein `app/Jobs`, keine Events/Listener; Mails synchron | fehlt für Newsletter-Versand und Social-Publishing |
| Marketing | `Newsletter`, `SocialPost` und `Article` greifen nicht auf Order oder Product zu | sauber getrennt, aber noch ohne Seam für Kanäle |

## Leitlinien

- **Kein Modul-Paket** (z. B. `nwidart/laravel-modules`). Bei dieser Größe und einem Team bringt es mehr Konfiguration und mehr Interface, aber keine Tiefe.
- **Laravel-Orte bleiben:** Controller, Requests, Models, Mails, Migrationen bleiben, wo Laravel und Wayfinder sie erwarten. Fachgebiets-Namespaces gibt es nur für die Geschäftslogik.
- **Ein PR je Phase**, jede Phase einzeln nutzbar und abbrechbar. Kein Big Bang.
- **Bestehende Feature-Tests sind das Sicherheitsnetz.** Sie bleiben grün und werden nicht umgeschrieben, um grün zu werden. Neue Tests laufen direkt gegen das Interface des vertieften Moduls.
- **Keine Verhaltensänderung** in den Refactoring-Phasen 1, 2 und 4. Wo das Review Lücken zeigt (offene Fragen unten), entscheidest du vorher.
- **DB-Werte bleiben gleich.** Enums sind String-backed mit den heutigen Werten, es braucht also keine Daten-Migration.

## Phasen im Überblick

| Phase | Inhalt | Kandidat im Review | Empfehlung | Abhängigkeit |
|---|---|---|---|---|
| 0 | Glossar und erste ADRs | – | vorab | – |
| 1 | Ein Modul für den Bestell-Lebenszyklus | #1 | **stark** | 0 |
| 2 | PayPal wird ein echter Adapter | #2 | **stark** | 1 |
| 3 | Admin-Tabellen als ein Modul | #3 | lohnt sich zu prüfen | – (parallel möglich) |
| 4 | Shop-Einstellungen mit festen Schlüsseln | #4 | lohnt sich zu prüfen | – |
| 5 | Kanal-Seam, Queue und Jobs für Marketing | #5 | erst mit dem ersten echten Kanal | API-Freigaben, Newsletter Phase 2 |
| 6 | Namespaces nach Fachgebiet | Modul-Frage | optional | 1, 2 |

---

## Phase 0: Glossar und Entscheidungen festhalten

**Ziel:** Die Begriffe des Shops und die Grundsatzentscheidungen stehen an einer Stelle, damit spätere Reviews und Agents sie nicht neu verhandeln.

- [ ] `GLOSSARY.md` im Repo-Root anlegen, mit diesen Fachbegriffen:
  - Bestellung (Order), Zahlung (Payment), Bezahlart (`invoice`/`paypal`), Bestellstatus, Zahlungsstatus, Capture, Erstattung, Abgleich (Mismatch);
  - Warenkorb, Katalog, Kategorie, Fachrichtung;
  - Newsletter, Social-Beitrag, Magazin-Artikel, Kanal;
  - Sandbox/Live-Modus.
- [ ] `docs/adr/0001-fachgebiets-namespaces-statt-modul-paket.md`: Entscheidung aus den Leitlinien.
- [ ] `docs/adr/0002-kein-fulfillment-status.md`: heute nur in AGENTS.md; der neue Lebenszyklus darf den Status nicht wieder einführen.

> **Freigabe nötig:** `docs/adr/` ist ein neuer Basisordner (CLAUDE.md: „don't create new base folders without approval“).

**Fertig, wenn:** Beide Dateien sind gemergt, und AGENTS.md verweist darauf.

---

## Phase 1: Ein Modul für den Bestell-Lebenszyklus

**Ziel:** Ein Modul entscheidet, welche Übergänge von Bestellung und Zahlung erlaubt sind. Alle anderen fragen es, statt Status selbst zu schreiben.

### 1.1 Statuswerte als Enums

| Enum | Werte (unverändert aus der DB) | Ersetzt |
|---|---|---|
| `OrderStatus` | `pending`, `paid`, `failed`, `cancelled`, `refunded` | Strings in PayPalController, Admin/OrderController, OrderManager, Dashboard, Customer, CustomerOrderController, CheckoutController |
| `PaymentStatus` | `CREATED`, `APPROVED`, `PENDING`, `COMPLETED`, `FAILED`, `MISMATCH`, `REFUNDED`, `REVERSED` | Strings in PayPalController, PayPalService, Admin/OrderController |
| `PaymentMethod` | `invoice`, `paypal` | Strings in PayPalController, CheckoutController, Admin/OrderController, `config/shop.php` |

- [ ] Enums anlegen, mit Label und Endzustand je Wert.
- [ ] Als Casts auf `Order::status`, `Order::payment_method` und `Payment::status`.
- [ ] TypeScript-Typen für das Frontend daraus ableiten: entweder als Prop (Labels vom Server) oder generiert. Ersetzt die Listen in `lib/orderStatus.ts` und `Admin/Orders/Show.svelte`.

### 1.2 Erlaubte Übergänge (heutiges Verhalten, aus dem Code gelesen)

| Von → Nach | Auslöser heute | Stelle heute |
|---|---|---|
| (neu) → `pending` | Bestellung anlegen | `OrderManager::createFromCart` |
| `pending` → `paid` | PayPal-Capture oder Webhook `CAPTURE.COMPLETED` | `OrderManager::markPaid` / `transitionToPaid` |
| `pending` → `paid` | Admin bestätigt Zahlungseingang (nur `invoice`) | `OrderManager::confirmInvoicePayment` über `Admin/OrderController::confirmPayment` |
| `pending` → `failed` | Capture fehlgeschlagen, Webhook `DENIED` | `PayPalController` (:73, :95, :392, :424, :637) |
| `pending` → `cancelled` | veraltete PayPal-Bestellung beim nächsten Versuch | `PayPalController::cancelStalePendingPayPalOrders` |
| `paid` → `refunded` | Admin-Erstattung, Webhook `REFUNDED`/`REVERSED` | `Admin/OrderController::refund`, `PayPalController` (:606, :678) |
| `failed`/`cancelled` → `paid` | verspäteter Capture | `transitionToPaid` schließt nur `paid`/`refunded` aus |

**Endzustände heute:** `paid` (außer → `refunded`) und `refunded`.

### 1.3 Das Modul

- [ ] Ein Modul im Fachgebiet Orders bündelt:
  - das Anlegen aus dem Warenkorb;
  - alle Übergänge der Tabelle (bezahlt, fehlgeschlagen, storniert, erstattet);
  - die Prüfung, ob ein Übergang erlaubt ist (statt 4× `whereNotIn('status', ['paid', 'refunded'])`);
  - die Mails, die an Übergängen hängen. Die zwei Mail-Pfade aus AGENTS.md bleiben getrennt.
- [ ] Idempotenz bleibt wie heute: atomarer Statuswechsel per `update … where`, keine doppelten Mails.
- [ ] `OrderManager` wird zu diesem Modul ausgebaut oder darin aufgelöst; die genaue Form wird vor der Umsetzung entworfen.
- [ ] `summaryFromOrder` (Darstellung für Mails und Seiten) zieht aus dem Lebenszyklus heraus, in ein eigenes kleines Modul oder ins Model.
- [ ] Die MwSt.-Formel steht nur noch an einer Stelle (heute doppelt in `Cart.php:109` und `OrderManager.php:197`).
- [ ] Die Umsatz-Definition („bezahlte Bestellungen“) kommt als Scope ins Model und wird von `DashboardController` und `Customer` genutzt.

### 1.4 Aufrufer umstellen

- [ ] `PayPalController`: keine direkten `->update(['status' => …])` mehr.
- [ ] `Admin/OrderController::refund` und `confirmPayment` nutzen das Modul. Die Prüfung „nur `invoice` und `pending`“ wandert hinein.
- [ ] `CheckoutController`, `CustomerOrderController`, `Customer::paidOrderContaining` und `DashboardController` lesen über die Enums oder Scopes.

### 1.5 Tests

- [ ] Neuer Test direkt gegen das Modul: jede Zeile der Übergangstabelle, dazu jeder verbotene Übergang (z. B. `refunded` → `paid`) und die Mails pro Übergang (`Mail::fake`).
- [ ] Bestehende Tests bleiben unverändert grün: `PayPalCaptureTest`, `PayPalWebhookTest`, `PayPalReturnFlowTest`, `PayPalPaymentLifecycleTest`, `OrderManagerTest`, `Admin/OrderManagementTest`.
- [ ] PHPStan Level 7 ohne neue Baseline-Einträge.

**Fertig, wenn:**
- `grep -rn "'paid'\|'refunded'\|'failed'\|'cancelled'" app/` findet nur noch die Enum-Definition.
- AGENTS.md (Abschnitt Zahlungen) beschreibt das Modul als einzige Stelle, und das stimmt wieder.

**Aufwand:** mittel, ein PR. Das Risiko ist durch die bestehenden PayPal-Tests gedeckt.

---

## Phase 2: PayPal wird ein echter Adapter

**Ziel:** PayPal liefert nur noch neutrale Ergebnisse. Die Entscheidungen fallen im Lebenszyklus aus Phase 1. Der Controller kümmert sich nur noch um HTTP.

- [ ] **PayPal-Adapter** (aus `PayPalService`):
  - übernimmt Order anlegen, Capture, Erstattung und Webhook-Prüfung;
  - liefert neutrale Ergebnisse (Status, Betrag, Währung, Capture-ID, Rohdaten fürs Log);
  - schreibt keine Models mehr (`recordPayment` zieht um);
  - parst das PayPal-JSON an genau einer Stelle (heute im Controller :296, :501 und im Service :218, :230).
- [ ] **Zahlungsabgleich** (neues Modul im Fachgebiet Orders/Payment):
  - nimmt Capture- und Webhook-Ergebnisse entgegen;
  - prüft Betrag und Währung (heute `captureMatchesOrder`);
  - löst Mismatch und Betreiber-Mail aus;
  - ruft die Übergänge aus Phase 1 auf;
  - Return-Pfad und Webhook-Pfad teilen sich diese Logik.
- [ ] `PayPalController` behält nur noch Request annehmen, Signatur prüfen lassen, Abgleich aufrufen und Antwort bauen. Ziel: unter 200 Zeilen.
- [ ] **Fake-Adapter für Tests:** Erst damit wird der Seam echt; er ist der zweite Adapter neben PayPal live.
- [ ] Tests für den Abgleich direkt gegen den Fake: Capture ok, Mismatch, Pending, Denied, Refund, Reversed, doppelte Webhooks.
- [ ] Die bestehenden PayPal-Feature-Tests mit den OpenAPI-Fixtures (`tests/Fixtures/paypal`) bleiben unverändert grün.

**Fertig, wenn:**
- Kein PayPal-JSON-Pfad steht außerhalb des Adapters.
- Kein Model wird im Adapter geschrieben.
- Ein drittes Gateway bräuchte nur einen neuen Adapter.

**Aufwand:** mittel bis groß, ein PR. **Voraussetzung:** Phase 1 ist gemergt.

---

## Phase 3: Admin-Tabellen als ein Modul (Frontend)

**Ziel:** Eine neue Admin-Liste beschreibt nur noch Daten, Spalten, Suchfelder und Aktionen. Die tanstack-Verdrahtung gibt es einmal.

- [ ] Ein Svelte-Modul für Admin-Tabellen. Es kapselt:
  - Auswahl-Spalte, Sortierung, globale Suche, Spalten-Dropdown;
  - Leer-Zustand und Tabellen-Markup.
- [ ] Ein Status-Badge, das aus einer Statustabelle (Label und Farbe je Wert) gespeist wird. Ersetzt die 3 kopierten `StatusBadge.svelte` (Articles, Newsletters, SocialPosts); Orders nutzt bereits `lib/orderStatus.ts`.
- [ ] Ein Aktionsmenü mit „Bearbeiten“ und „Löschen“ plus optionalen Einträgen. Ersetzt die 5 `*Actions.svelte`.
- [ ] Die 7 `Index.svelte` umstellen, die einfachsten zuerst (Manufacturers, Articles, SocialPosts).
- [ ] Vitest-Tests für das Modul: Suche, Sortierung, Spalten aus- und einblenden, Leer-Zustand, Aktionen.
- [ ] Danach einmal bauen statt siebenmal: die Ideen aus TODO.md (Massenaktionen, Statusfilter).

**Fertig, wenn:**
- Jede Index-Seite hat unter 100 Zeilen.
- Es gibt keine kopierten Badges oder Aktionsmenüs mehr.

**Aufwand:** mittel, gut in zwei PRs teilbar. Unabhängig von Phase 1 und 2, kann also parallel laufen.

---

## Phase 4: Shop-Einstellungen mit festen Schlüsseln

**Ziel:** Jede Einstellung hat einen Namen, einen Standardwert und eine Stelle.

- [ ] Ein Modul „Shop-Einstellungen“ mit benannten Zugriffen (E-Mail, Telefon, Bankdaten …) über `Setting`.
- [ ] Die erlaubten Schlüssel ziehen aus `Admin/SettingController::ALLOWED_KEYS` dort hinein.
- [ ] Standardwerte einheitlich. Heute widersprechen sich `HandleInertiaRequests` (`02301 – 188600`) und die Blade-Mails (`02301188600`).
- [ ] Aufrufer umstellen: `OrderManager`, `PayPalController`, `NewsletterController`, `ModeMailConfiguration`, `HandleInertiaRequests` und die drei Blade-Mails. Mails bekommen die Werte übergeben, statt selbst `Setting::get` aufzurufen.
- [ ] Tests: Standardwerte, gespeicherte Werte, unbekannter Schlüssel.

**Aufwand:** klein, ein PR.

---

## Phase 5: Kanal-Seam, Queue und Jobs für Marketing

**Auslöser:** erst, wenn die LinkedIn- und Meta-Freigaben da sind oder Newsletter Phase 2 (Abonnenten, Versand) startet. Vorher wäre der Seam nur hypothetisch.

- [ ] Queue einrichten: Treiber `database` existiert schon, dazu der Worker im Deploy. Das ist eine Entscheidung zum Betrieb (siehe offene Fragen).
- [ ] **Social:**
  - ein Interface „Beitrag veröffentlichen“ mit zwei Adaptern, LinkedIn und Facebook;
  - Status über ein Enum wie in Phase 1;
  - ein Scheduler-Befehl jede Minute, der fällige Beiträge (`scheduled_at <= now`) als Job einreiht;
  - ein Fake-Adapter für Tests.
- [ ] **Newsletter:**
  - Versand-Jobs aus `.todo/newsletter-editor-grapesjs-mjml-laravel-plan.md` (Phase 2) über dieselbe Queue;
  - Double-Opt-in als eigenes Modul im Fachgebiet Marketing.
- [ ] **Events, wo mehrere Gebiete reagieren:** etwa „Bestellung bezahlt“, damit Newsletter-Opt-in oder Bewertungseinladung nicht in den Lebenszyklus geschrieben werden müssen.

**Aufwand:** groß, in mehreren PRs, getrennt nach Social und Newsletter.

---

## Phase 6 (optional): Namespaces nach Fachgebiet

**Erst nach Phase 1 und 2**, wenn es tiefe Module gibt, die man sinnvoll ordnen kann.

```
app/Domain/
  Core/        Money, Shop-Einstellungen, PaymentMode
  Shop/        Cart (heute Support/Cart), Catalog, Products
  Orders/      Lebenszyklus, Zahlungsabgleich, PayPal-Adapter
  Marketing/   Newsletter (HtmlSanitizer, Versand), Social (Kanäle), Magazin (Markdown, Slug)
```

- [ ] Geschäftslogik aus `app/Support/*` und `app/Services/*` umziehen, ein Fachgebiet pro PR.
- [ ] Controller, Requests, Models, Mails und Migrationen bleiben an den Laravel-Orten.
- [ ] Abhängigkeitsrichtung: Marketing → Core; Orders → Shop → Core. Marketing greift nie auf Orders zu, sondern reagiert auf Events.
- [ ] Optional ein Architekturtest (z. B. per PHPStan-Regel oder einfachem Test), der diese Richtung prüft.

> **Freigabe nötig:** `app/Domain` ist ein neuer Basisordner.

---

## Offene Fragen an dich

1. **Verspäteter Capture nach `failed` oder `cancelled`:** Heute wird die Bestellung dann `paid` (Geld ist da). Bleibt das so? Mein Vorschlag: ja, und genau so in der Übergangstabelle festhalten.
2. **Freigaben für die neuen Ordner** `docs/adr/` (Phase 0) und `app/Domain/` (Phase 6)?
3. **Reihenfolge:** Phase 3 (Frontend) parallel zu Phase 1 oder danach?
4. **Queue-Worker auf dem Server** (Phase 5): als Systemdienst oder per Cron (`queue:work --stop-when-empty`)?

## Nicht-Ziele

- Kein Modul-Paket, kein Umbau der Laravel-Grundstruktur.
- Kein Fulfillment-Status (siehe AGENTS.md, Betriebsmodell).
- Keine dritte Bezahlart in diesem Plan.
- Keine Änderung am Sandbox/Live-Modus. Dafür gibt es einen eigenen Plan: `.todo/sandbox-live-schema-trennung.md`.
