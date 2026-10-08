# Plan: Architektur vertiefen, dann nach Fachgebieten ordnen

> Status: **Phase 0 und 1 in Umsetzung** (gleicher PR wie dieser Plan). Grundlage ist das Architektur-Review vom 08.10.2026 (Stand `main` nach PR #28 plus PR #29, Social-Media und Magazin).

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
| 1 | Ein Modul für den Bestell-Lebenszyklus | #1 | **stark**, erledigt | 0 |
| 2 | PayPal wird ein echter Adapter | #2 | **stark**, erledigt | 1 |
| 3 | Admin-Tabellen als ein Modul | #3 | lohnt sich zu prüfen | 2 (sequenziell danach) |
| 4 | Shop-Einstellungen mit festen Schlüsseln | #4 | erledigt (Config statt Admin) | – |
| 5 | Kanal-Seam, Queue und Jobs für Marketing | #5 | erst mit dem ersten echten Kanal | API-Freigaben, Newsletter Phase 2 |
| 6 | Namespaces nach Fachgebiet unter `app/Support` | Modul-Frage | optional | 1, 2 |

---

## Phase 0: Glossar und Entscheidungen festhalten

**Ziel:** Die Begriffe des Shops und die Grundsatzentscheidungen stehen an einer Stelle, damit spätere Reviews und Agents sie nicht neu verhandeln.

- [x] `GLOSSARY.md` im Repo-Root anlegen, mit diesen Fachbegriffen:
  - Bestellung (Order), Zahlung (Payment), Bezahlart (`invoice`/`paypal`), Bestellstatus, Zahlungsstatus, Capture, Erstattung, Abgleich (Mismatch);
  - Warenkorb, Katalog, Kategorie, Fachrichtung;
  - Newsletter, Social-Beitrag, Magazin-Artikel, Kanal;
  - Sandbox/Live-Modus.
- [x] `.docs/adr/0001-fachgebiets-namespaces-statt-modul-paket.md`: Entscheidung aus den Leitlinien.
- [x] `.docs/adr/0002-kein-fulfillment-status.md`: heute nur in AGENTS.md; der neue Lebenszyklus darf den Status nicht wieder einführen.

- [x] `.docs/adr/0003-verspaeteter-capture-macht-bestellung-bezahlt.md`: Entscheidung zu Frage 1 (siehe unten).

> **Freigegeben:** ADRs liegen in `.docs/adr/` (Entscheidung vom 08.10.2026).

**Fertig, wenn:** Glossar und ADRs sind gemergt, und AGENTS.md verweist darauf. ✅ (AGENTS.md, Abschnitt „Architektur-Entscheidungen und Begriffe“)

---

## Phase 1: Ein Modul für den Bestell-Lebenszyklus

**Ziel:** Ein Modul entscheidet, welche Übergänge von Bestellung und Zahlung erlaubt sind. Alle anderen fragen es, statt Status selbst zu schreiben.

### 1.1 Statuswerte als Enums

| Enum | Werte (unverändert aus der DB) | Ersetzt |
|---|---|---|
| `OrderStatus` | `pending`, `paid`, `failed`, `cancelled`, `refunded` | Strings in PayPalController, Admin/OrderController, OrderManager, Dashboard, Customer, CustomerOrderController, CheckoutController |
| `PaymentStatus` | `CREATED`, `APPROVED`, `PENDING`, `COMPLETED`, `FAILED`, `MISMATCH`, `REFUNDED`, `REVERSED` | Strings in PayPalController, PayPalService, Admin/OrderController |
| `PaymentMethod` | `invoice`, `paypal` | Strings in PayPalController, CheckoutController, Admin/OrderController, `config/shop.php` |

- [x] Enums anlegen, mit Label und Endzustand je Wert.
- [x] Als Casts auf `Order::status`, `Order::payment_method` und `Payment::status`.
- [ ] → **nach Phase 3 verschoben:** TypeScript-Typen für das Frontend daraus ableiten: entweder als Prop (Labels vom Server) oder generiert. Ersetzt die Listen in `lib/orderStatus.ts` und `Admin/Orders/Show.svelte`.

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

- [x] Ein Modul im Fachgebiet Orders bündelt:
  - das Anlegen aus dem Warenkorb;
  - alle Übergänge der Tabelle (bezahlt, fehlgeschlagen, storniert, erstattet);
  - die Prüfung, ob ein Übergang erlaubt ist (statt 4× `whereNotIn('status', ['paid', 'refunded'])`);
  - die Mails, die an Übergängen hängen. Die zwei Mail-Pfade aus AGENTS.md bleiben getrennt.
- [x] Idempotenz bleibt wie heute: atomarer Statuswechsel per `update … where`, keine doppelten Mails.
- [x] `OrderManager` wird zu diesem Modul ausgebaut oder darin aufgelöst; die genaue Form wird vor der Umsetzung entworfen.
- [x] `summaryFromOrder` (Darstellung für Mails und Seiten) zieht aus dem Lebenszyklus heraus, in ein eigenes kleines Modul oder ins Model.
- [x] Die MwSt.-Formel steht nur noch an einer Stelle (heute doppelt in `Cart.php:109` und `OrderManager.php:197`).
- [x] Die Umsatz-Definition („bezahlte Bestellungen“) kommt als Scope ins Model und wird von `DashboardController` und `Customer` genutzt.

### 1.4 Aufrufer umstellen

- [x] `PayPalController`: keine direkten `->update(['status' => …])` mehr.
- [x] `Admin/OrderController::refund` und `confirmPayment` nutzen das Modul. Die Prüfung „nur `invoice` und `pending`“ wandert hinein.
- [x] `CheckoutController`, `CustomerOrderController`, `Customer::paidOrderContaining` und `DashboardController` lesen über die Enums oder Scopes.

### 1.5 Tests

- [x] Neuer Test direkt gegen das Modul: jede Zeile der Übergangstabelle, dazu jeder verbotene Übergang (z. B. `refunded` → `paid`) und die Mails pro Übergang (`Mail::fake`).
- [x] Bestehende Tests bleiben inhaltlich unverändert grün (nur Vergleichswerte auf Enums umgestellt; zwei `OrderManagerTest`-Fälle bekommen die Bezahlart „Rechnung“, weil Zahlungseingang nur noch für Rechnungsbestellungen geht): `PayPalCaptureTest`, `PayPalWebhookTest`, `PayPalReturnFlowTest`, `PayPalPaymentLifecycleTest`, `OrderManagerTest`, `Admin/OrderManagementTest`.
- [ ] PHPStan Level 7 ohne neue Baseline-Einträge (prüft die CI).

**Fertig, wenn:**
- Bestellstatus werden nirgends mehr als String gesetzt oder verglichen. ✅ (Die verbleibenden Treffer für `'failed'` usw. sind `SocialPost`, das interne Capture-Ergebnis und PayPal-JSON im `PayPalController`; Letztere sind Phase 2.)
- AGENTS.md (Abschnitt Zahlungen) beschreibt das Modul als einzige Stelle, und das stimmt wieder.

**Aufwand:** mittel, ein PR. Das Risiko ist durch die bestehenden PayPal-Tests gedeckt.

### 1.6 Umsetzung (Stand)

- Enums liegen in `app/Support/Orders/` (`OrderStatus`, `PaymentStatus`, `PaymentMethod`), nicht in einem neuen `app/Enums`, siehe ADR 0001.
- `OrderManager::transitionTo()` ist der eine Übergang; `markPaid`, `confirmInvoicePayment` (prüft jetzt selbst `canConfirmInvoicePayment`) und `cancelAbandonedPayPalOrders` bauen darauf auf. `transitionToPaid` entfällt.
- `OrderSummary::fromOrder()` ersetzt `OrderManager::summaryFromOrder()`; MwSt. über `Money::netCents()`, auch im Warenkorb.
- `Order::scopePaid()` für Umsatz (Dashboard) und Bewertungsrecht (`Customer`).
- `OrderFactory` erzeugte noch Status aus der Zeit vor ADR 0002 (`processing`, `shipped`, `delivered`); jetzt immer `pending`.
- Zahlungsstatus werden im `PayPalController` noch direkt gesetzt (jetzt als Enum). Das zieht in Phase 2 in den Zahlungsabgleich.
- Die übrigen Strings im `PayPalController` sind PayPal-JSON und Antworten an das Frontend: Phase 2.

---

## Phase 2: PayPal wird ein echter Adapter

**Ziel:** PayPal liefert nur noch neutrale Ergebnisse. Die Entscheidungen fallen im Lebenszyklus aus Phase 1. Der Controller kümmert sich nur noch um HTTP.

- [x] **PayPal-Adapter** (aus `PayPalService`):
  - übernimmt Order anlegen, Capture, Erstattung und Webhook-Prüfung;
  - liefert neutrale Ergebnisse (Status, Betrag, Währung, Capture-ID, Rohdaten fürs Log);
  - schreibt keine Models mehr (`recordPayment` zieht um);
  - parst das PayPal-JSON an genau einer Stelle (heute im Controller :296, :501 und im Service :218, :230).
- [x] **Zahlungsabgleich** (neues Modul im Fachgebiet Orders/Payment):
  - nimmt Capture- und Webhook-Ergebnisse entgegen;
  - prüft Betrag und Währung (heute `captureMatchesOrder`);
  - löst Mismatch und Betreiber-Mail aus;
  - ruft die Übergänge aus Phase 1 auf;
  - Return-Pfad und Webhook-Pfad teilen sich diese Logik.
- [x] `PayPalController` behält nur noch Request annehmen, Signatur prüfen lassen, Abgleich aufrufen und Antwort bauen. Ziel: unter 200 Zeilen.
- [x] **Fake-Adapter für Tests:** Erst damit wird der Seam echt; er ist der zweite Adapter neben PayPal live.
- [x] Tests für den Abgleich direkt gegen den Fake: Capture ok, Mismatch, Pending, Denied, Refund, Reversed, doppelte Webhooks.
- [x] Die bestehenden PayPal-Feature-Tests mit den OpenAPI-Fixtures (`tests/Fixtures/paypal`) bleiben unverändert grün.

### 2.1 Umsetzung (Stand)

- **Seam:** `App\Support\Orders\Gateway\PaymentGateway` mit den neutralen Ergebnissen `CreatedPayment`, `CaptureResult`, `RefundResult` und `WebhookEvent` (`WebhookEventType`). Status kommen als `PaymentStatus`.
- **PayPal-Adapter:** `PayPalService` bleibt der SDK-Client und liefert rohes JSON; `App\Services\PayPalGateway` übersetzt es. Das ist die einzige Stelle, die PayPal-JSON liest. `recordPayment` und `getCaptureIdFromOrder` sind aus `PayPalService` entfernt.
- **Zahlungsabgleich:** `App\Support\Orders\PaymentReconciliation` mit `start`, `capture`, `refund` und `handleWebhook`. Betragsabgleich, Abweichung samt Betreiber-Mail, Wettlauf zwischen Capture und Webhook sowie Teilerstattungen liegen hier. Bestell-Übergänge laufen weiter nur über den `OrderManager`.
- **Controller:** `PayPalController` hat 189 statt 724 Zeilen. `Admin/OrderController::refund` ruft den Abgleich auf.
- **Tests:**
  - `PaymentReconciliationTest` gegen `Tests\Support\FakePaymentGateway` (27 Fälle, inkl. Webhook-Route mit dem Fake).
  - `PayPalGatewayTest` gegen die OpenAPI-Fixtures.
  - In den bestehenden PayPal-Tests wurden nur die Stellen angepasst, die `recordPayment`/`getCaptureIdFromOrder` direkt aufriefen.
- **Bewusste Verhaltensänderungen:**
  - Die Return-URL markiert einen gescheiterten Capture jetzt wie der JS-Capture als fehlgeschlagen; vorher blieb die Bestellung offen. Ein späterer Capture-Webhook macht sie trotzdem bezahlt (ADR 0003).
  - Scheitert das Anlegen bei PayPal mit einer Exception, lautet die Meldung jetzt einheitlich „PayPal-Order konnte nicht erstellt werden.“
  - Der Webhook sucht eine Zahlung nicht mehr über einen JSON-Pfad in `response_data`, sondern über Capture-ID oder Order-Referenz. Der JSON-Pfad war seit dem Speichern der Capture-ID tot.
- **Offen für ein drittes Gateway:** Die Spalten heißen noch `paypal_order_id`/`paypal_capture_id`. Ein zweiter Zahlungsdienst bräuchte eine Migration auf neutrale Namen; bis dahin bleibt das so.

**Fertig, wenn:**
- Kein PayPal-JSON-Pfad steht außerhalb des Adapters.
- Kein Model wird im Adapter geschrieben.
- Ein drittes Gateway bräuchte nur einen neuen Adapter.

**Aufwand:** mittel bis groß, ein PR. **Voraussetzung:** Phase 1 ist gemergt. **Stand:** umgesetzt.

---

## Phase 3: Admin-Tabellen als ein Modul (Frontend)

**Ziel:** Eine neue Admin-Liste beschreibt nur noch Daten, Spalten, Suchfelder und Aktionen. Die tanstack-Verdrahtung gibt es einmal.

- [x] Ein Svelte-Modul für Admin-Tabellen. Es kapselt:
  - Auswahl-Spalte, Sortierung, globale Suche, Spalten-Dropdown;
  - Leer-Zustand und Tabellen-Markup.
- [x] Ein Status-Badge, das aus einer Statustabelle (Label und Farbe je Wert) gespeist wird. Ersetzt die 3 kopierten `StatusBadge.svelte` (Articles, Newsletters, SocialPosts); Orders nutzt bereits `lib/orderStatus.ts`.
- [x] Ein Aktionsmenü mit „Bearbeiten“ und „Löschen“ plus optionalen Einträgen. Ersetzt die 5 `*Actions.svelte`.
- [x] Die 7 `Index.svelte` umstellen, die einfachsten zuerst (Manufacturers, Articles, SocialPosts).
- [x] Status-Labels und -Typen für das Frontend aus den PHP-Enums (aus Phase 1 verschoben). **Entschieden:** Jedes Enum liefert Label und Ton selbst, die Seiten bekommen die Zuordnung als Prop vom Server (eine Quelle in PHP, keine gepflegte TS-Kopie): ersetzt die Listen in `lib/orderStatus.ts` und `Admin/Orders/Show.svelte`.
- [x] Vitest-Tests für das Modul: Suche, Sortierung, Spalten aus- und einblenden, Leer-Zustand, Aktionen.
- [ ] Danach einmal bauen statt siebenmal: die Ideen aus TODO.md (Massenaktionen, Statusfilter).

### 3.1 Umsetzung (Stand)

**PR 1 (gemergt):**
- `components/AdminDataTable.svelte`: Auswahl-Spalte, Sortierung per Klick auf die Spaltenüberschrift (neu, vorher war Sortieren verdrahtet, aber nicht bedienbar), globale Suche über `searchFields`, Spalten-Dropdown, Leer-Zustand mit getrenntem Text für „noch nichts angelegt“ und „Suche ohne Treffer“, optionaler `toolbar`-Snippet für weitere Filter.
- `components/RowActions.svelte`: „Bearbeiten“ (nur mit `editHref`), eigene Einträge per `items`-Snippet, „Löschen“ mit Rückfrage.
- `components/StatusBadge.svelte` mit `lib/status.ts` (`StatusMap`, Töne). Die Zuordnungen für Magazin und Social-Media stehen bis PR 2 noch in `lib/status.ts`.
- Umgestellt: Hersteller, Magazin, Social-Media (Liste und Bearbeiten-Seite). Entfernt: 3 `*Actions.svelte`, 2 `StatusBadge.svelte`.
- Index-Seiten: Hersteller 92, Magazin 122, Social-Media 132 Zeilen (vorher 245/287/295). Die Restlänge sind die Spaltendefinitionen.

**PR 2 (dieser Stand):**
- Status aus PHP: Trait `App\Support\HasStatusBadge` (Label + `StatusTone` je Fall, `badges()` als Prop) auf `OrderStatus` und den neuen Enums `ArticleStatus` (`Support/Magazine`), `SocialPostStatus` (`Support/Social`), `NewsletterStatus` (`Support/Newsletter`). `SocialPost` und `Newsletter` casten `status` auf ihr Enum, `Article::status()` liefert `ArticleStatus`; die `STATUS_*`-Konstanten sind weg.
- Bestellstatus als geteilte Prop `orderStatuses` (Admin und Kundenkonto); Magazin, Social-Media und Newsletter bekommen `statuses` je Seite. `lib/orderStatus.ts` und die TS-Zuordnungen sind entfernt.
- `AdminDataTable` neu: `rowHref`/`rowLabel` (Zeile als Link, Bestellungen) und `serverSearch` (Suche auf dem Server, Kunden). `RowActions`: `links` statt Snippet (lässt sich per `renderComponent` übergeben), „Löschen“ nur mit `destroyHref` (verschickte Newsletter).
- Umgestellt: Kunden, Produkte (Kategorie-Filter als `toolbar`), Bestellungen, Newsletter. Entfernt: `ProductActions`, `NewsletterActions`, die Status-Badges von Bestellungen und Newsletter.
- Index-Seiten jetzt 92–148 Zeilen (vorher 245–295). Unter 100 liegen Hersteller, Kunden und Bestellungen; bei den anderen sind es die Spaltendefinitionen. Das Ziel „unter 100“ ist damit nicht ganz erreicht, eine weitere Kürzung würde nur Spalten in eigene Dateien verschieben.

**Fertig, wenn:**
- Jede Index-Seite hat unter 100 Zeilen.
- Es gibt keine kopierten Badges oder Aktionsmenüs mehr.

**Aufwand:** mittel, gut in zwei PRs teilbar. Technisch unabhängig von Phase 1 und 2, läuft aber **sequenziell nach Phase 2** (entschieden).

---

## Phase 4: Shop-Einstellungen mit festen Schlüsseln

**Ziel:** Jede Einstellung hat einen Namen, einen Standardwert und eine Stelle.

**Entschieden (anders als ursprünglich geplant):** Keine Konfiguration mehr im Admin (`.docs/adr/0004-keine-konfiguration-im-admin.md`). Kontakt und Bankverbindung stehen direkt in `config/shop.php`, der Shopname ist `APP_NAME`. Telefon und Fax als Vorwahl + Rufnummer, weil sich aus `02301188600` allein nicht ablesen lässt, wo die Vorwahl endet. Versandarten bleiben im Admin (Geschäftsdaten). Die Tabelle `settings` wird per Migration gelöscht.

- [x] `App\Support\ShopContact` mit benannten Zugriffen (`email()`, `phone()`, `fax()`, `bank()`, `name()`, `toArray()` für die geteilte Prop) und `App\Support\PhoneNumber` (`display()` „02301 / 188-600“, `href()` „tel:+492301188600“).
- [x] `Admin/SettingController`, Seite „Einstellungen → Allgemein“, `Setting`-Model, `ContactsSeeder` entfernt; `/admin/settings` leitet auf Versandarten.
- [x] Standardwerte an einer Stelle; der Widerspruch zwischen `HandleInertiaRequests` und den Mails ist weg.
- [x] Aufrufer umgestellt: `OrderManager`, `PaymentReconciliation`, `NewsletterController`, `ModeMailConfiguration`, `HandleInertiaRequests`; die Mails bekommen `ShopContact` übergeben.
- [x] Tests: Anzeige und Link der Nummern, geteilte Prop, Mails mit Kontakt und Bankverbindung, alte Admin-Seite liefert 404/405.

**Offen:** IBAN und BIC sind in `config/shop.php` noch leer; bis sie eingetragen sind, zeigen Rechnungsmails „auf Anfrage“.

---

## Phase 5: Kanal-Seam, Queue und Jobs für Marketing

**Auslöser:** erst, wenn die LinkedIn- und Meta-Freigaben da sind oder Newsletter Phase 2 (Abonnenten, Versand) startet. Vorher wäre der Seam nur hypothetisch.

- [ ] Queue einrichten: Treiber `database` existiert schon. Der Worker (`queue:work`) läuft als **Systemdienst im Docker-Container** (entschieden). Das Deployment wird auf Docker umgestellt; der Worker-Dienst gehört in dieses Container-Setup, nicht in einen Cron auf dem Host.
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

Entschieden: **vorerst kein `app/Domain`**, die Fachgebiete bleiben unter `app/Support/<Fachgebiet>` (wie `app/Support/Orders` und `app/Support/Cart` heute, passend zu ADR 0001).

```
app/Support/
  Core/        Money, Shop-Einstellungen, PaymentMode
  Shop/        Cart (heute Support/Cart), Catalog, Products
  Orders/      Lebenszyklus, Zahlungsabgleich, PayPal-Adapter
  Marketing/   Newsletter (HtmlSanitizer, Versand), Social (Kanäle), Magazin (Markdown, Slug)
```

- [ ] Geschäftslogik aus `app/Support/*` und `app/Services/*` umziehen, ein Fachgebiet pro PR.
- [ ] Controller, Requests, Models, Mails und Migrationen bleiben an den Laravel-Orten.
- [ ] Abhängigkeitsrichtung: Marketing → Core; Orders → Shop → Core. Marketing greift nie auf Orders zu, sondern reagiert auf Events.
- [ ] Optional ein Architekturtest (z. B. per PHPStan-Regel oder einfachem Test), der diese Richtung prüft.

> Ein eigener Basisordner `app/Domain` wäre eine neue Freigabe und ist vorerst nicht vorgesehen.

---

## Offene Fragen an dich

1. ~~Verspäteter Capture nach `failed` oder `cancelled`~~ **Entschieden:** bleibt `paid`, siehe `.docs/adr/0003-…`.
2. ~~Freigabe für `app/Domain/`~~ **Entschieden:** vorerst unter `app/Support/<Fachgebiet>`, kein neuer Basisordner.
3. ~~Reihenfolge Phase 3~~ **Entschieden:** sequenziell, nach Phase 2.
4. ~~Queue-Worker auf dem Server~~ **Entschieden:** als Systemdienst im Docker-Container; das Deployment wird auf Docker umgestellt.

## Nicht-Ziele

- Kein Modul-Paket, kein Umbau der Laravel-Grundstruktur.
- Kein Fulfillment-Status (siehe AGENTS.md, Betriebsmodell).
- Keine dritte Bezahlart in diesem Plan.
- Keine Änderung am Sandbox/Live-Modus. Dafür gibt es einen eigenen Plan: `.todo/sandbox-live-schema-trennung.md`.
