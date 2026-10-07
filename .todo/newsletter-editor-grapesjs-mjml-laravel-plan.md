# Newsletter-Editor (GrapesJS + MJML) für dormed24

Stand 07.10.2026. Ursprünglicher Allgemein-Plan, auf den Shop zugeschnitten.

## Entscheidungen

| Thema | Entscheidung |
|---|---|
| Empfänger | Nur Abonnenten mit **Double-Opt-in**: Anmeldung im Footer und per Checkbox im Kundenkonto, Bestätigungsmail, Abmeldelink in jeder Mail. |
| Versand | **Eigenes SMTP** über die vorhandene Mail-Konfiguration (Sandbox/Live) und die **Laravel-Queue** (`database`). |
| MJML → HTML | **Im Browser** (`mjml-browser` im Editor). Der Server speichert MJML und HTML und säubert das HTML. Zur Laufzeit läuft kein Node-Prozess auf dem Server. |
| Umfang | **Phase 1:** Editor, Vorschau und Testmail an den Admin. **Phase 2:** Abonnenten, Versand und Abmeldung. |

## Rahmen im Shop

- **Stack:** Laravel 13, Inertia v3, Svelte 5, Wayfinder, Tailwind 4, PHPUnit, Vitest. Admin-Seiten liegen unter `resources/js/pages/Admin/…` und laufen über Inertia, nicht über eine separate `/api`.
- **Datenbank:** SQLite (Staging und lokal). JSON-Spalten sind dort `TEXT`, ein `jsonb` gibt es nicht. Der Editor-Zustand wird als JSON-String gespeichert, mit Cast `array`.
- **Admin:**
  - Es gibt genau einen Admin aus der Env (`EnvAdmin`, Guard `admin`), keine Admin-Tabelle. Ein `created_by` entfällt.
  - Routen kommen in die `ensure.admin`-Gruppe in `routes/admin.php`, mit dem Namen `admin.newsletters.*`.
- **Mail:**
  - `ModeMailConfiguration` wählt die SMTP-Zugangsdaten für Sandbox oder Live.
  - Shop-Mails gehen bisher synchron raus (bewusste Entscheidung in ANALYSE.md). Der Newsletter ist die Ausnahme und läuft über die Queue.
- **Queue-Betrieb:**
  - Auf dem Server gibt es keinen Supervisor.
  - Lösung: Ein Cron startet jede Minute `php artisan schedule:run`, der Scheduler startet `queue:work --stop-when-empty --max-time=50`. Den Cron muss der Betreiber einmalig anlegen.
- **Bilder:** Sie liegen auf der Disk `public` unter `storage/app/public/newsletters/…`, mit absoluter HTTPS-URL über `APP_URL`, wie die Produktbilder.
- **Neue Abhängigkeiten (Freigabe nötig):** `grapesjs`, `grapesjs-mjml` (bringt `mjml-browser` mit), beide nur als npm-Frontend-Pakete. Auf der PHP-Seite gibt es keine neuen Pakete.

## Datenmodell

### `newsletters` (Phase 1)

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | bigint | |
| `name` | string | intern |
| `subject` | string(150) | |
| `preheader` | string(200), nullable | Vorschautext |
| `status` | string | `draft`, `sending`, `sent`, `cancelled` |
| `design_json` | text, nullable | GrapesJS-Projektdaten, Cast `array`, **Quelle der Wahrheit** |
| `mjml` | longText, nullable | aus dem Editor |
| `html` | longText, nullable | im Browser kompiliert, serverseitig gesäubert |
| `sent_at` | timestamp, nullable | |
| timestamps | | |

Eine Versionierung kommt erst später. Bis dahin genügt `updated_at`.

### `newsletter_subscribers` (Phase 2)

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | bigint | |
| `email` | string, unique | normalisiert (lowercase) |
| `customer_id` | FK nullable, `nullOnDelete` | bei Anmeldung aus dem Konto |
| `token` | string, unique | für Bestätigung und Abmeldung |
| `confirmed_at` | timestamp, nullable | Double-Opt-in |
| `unsubscribed_at` | timestamp, nullable | |
| `consent_ip`, `consent_at` | | Nachweis der Einwilligung |
| timestamps | | |

### `newsletter_deliveries` (Phase 2)

| Spalte | Hinweis |
|---|---|
| `newsletter_id`, `subscriber_id` | unique zusammen, damit niemand doppelt beliefert wird |
| `status` | `pending`, `sent`, `failed` |
| `error` | nullable |
| `sent_at` | nullable |

## Phase 1: Editor, Vorschau, Testmail

### Backend

- **`app/Models/Newsletter.php`:** mit Factory, Status-Konstanten und `isEditable()` (nur `draft`).
- **`Admin/NewsletterController`:**
  - `index` und `create` als Inertia-Seiten
  - `store` legt einen Entwurf an und leitet zum Editor weiter
  - `edit` als Inertia-Seite
  - `update` speichert `design_json`, `mjml` und `html`, plus `subject` und `preheader`; das läuft als JSON, damit Autosave ohne Seitenwechsel geht
  - `duplicate` und `destroy`, letzteres nur für Entwürfe
  - `preview` liefert das gespeicherte HTML mit Beispielwerten für die Variablen
  - `sendTest` schickt eine Testmail an `shop.email` oder an eine angegebene Adresse
- **`UpdateNewsletterRequest`:**
  - Längen für Betreff und Preheader
  - Maximalgröße für `design_json` und `html`, z. B. 1 MB
  - nur Entwürfe dürfen bearbeitet werden
- **`Support/Newsletter/HtmlSanitizer`:**
  - DOM-basiert, ohne neue Abhängigkeit
  - entfernt `<script>`, `<iframe>`, `<object>`, `on*`-Attribute und `javascript:`-Links
  - lässt nur `https:`-, `mailto:`- und `tel:`-Links zu und nur Bilder von `APP_URL`
  - Das HTML aus dem Browser wird **nie** ungeprüft übernommen.
- **`Mail/NewsletterMail`:**
  - Betreff und Preheader
  - HTML mit ersetzten Variablen
  - Klartext-Fallback über `strip_tags`
- **Personalisierung (`Support/Newsletter/Personalizer`):**
  - feste Platzhalter `{{ first_name }}`, `{{ shop_name }}`, `{{ unsubscribe_url }}`
  - Ersetzung serverseitig erst beim Versand, Werte escaped
  - in der Testmail mit Beispielwerten
- **Bild-Upload:** `POST admin/newsletters/assets`.
  - erlaubt: jpg, png und webp bis 2 MB
  - Antwort im Format, das der GrapesJS-Asset-Manager erwartet
- **Routen:** in `routes/admin.php`, Wayfinder erzeugt die TypeScript-Funktionen.

### Frontend

- **Seiten:** `pages/Admin/Newsletters/Index.svelte` (Liste), `Create.svelte` und `Edit.svelte` (Editor-Seite).
- **`components/newsletter-editor/`:**
  - `NewsletterEditor.svelte`: GrapesJS mounten und zerstören (Svelte-5-`$effect`), mit Ladezustand
  - `grapes-config.ts`: Preset `grapesjs-mjml`, Asset-Manager mit Upload-Route, deutsche Labels
  - `blocks.ts`: Blöcke für das MVP (siehe unten)
  - `theme.ts`: Shop-Farben (`#1a3a5c`, `#1a6bbf`), Schrift, Button-Stil und Breite 600 px, einmal zentral als `mj-attributes`
  - `storage.ts`: Autosave mit Debounce von 3 s über `fetchJson` und Wayfinder-Route. Außerdem Speichern per Button und per Strg+S.
  - `variables.ts`: Auswahl der erlaubten Platzhalter
- **Kompilieren:** Beim Speichern wird MJML zu HTML kompiliert (`mjml-browser`). Fehler und Warnungen des Compilers erscheinen im Editor, Speichern geht trotzdem, denn der Entwurf bleibt erhalten.
- **Vorschau:** im iframe (`srcdoc`), umschaltbar zwischen Desktop (600 px) und Mobil (375 px).
- **Navigation:** Admin-Menüpunkt „Newsletter“.

### Blöcke im MVP

Text, Bild, Button, Trenner, Abstand, 1-, 2- und 3-spaltig, Logo-Kopf, Footer mit Impressum-Angaben (aus den Settings) und Abmeldelink.

Später: Produktkarte (Produkt aus dem Shop wählen, Bild, Preis und Link werden übernommen), Hero, Bild + Text.

### Tests (Phase 1)

- **Feature:**
  - CRUD und Rechte (nur Admin, Kunde bekommt 403)
  - nur Entwürfe sind bearbeitbar
  - Validierung (Längen, Größe)
  - Upload (Typ, Größe, öffentliche URL)
  - Vorschau ersetzt die Variablen
  - Testmail mit `Mail::fake` (Empfänger, Betreff, ersetzte Variablen)
- **Unit:**
  - `HtmlSanitizer`: Script, Event-Handler, `javascript:` und fremde Bilder werden entfernt; zulässiges MJML-HTML bleibt unverändert
  - `Personalizer`: Escaping, unbekannte Platzhalter bleiben stehen
- **Vitest:** `storage.ts` (Debounce, Fehleranzeige) und `variables.ts`. GrapesJS selbst wird nicht getestet, es wird gemockt.

### Definition of Done (Phase 1)

Ein Entwurf lässt sich anlegen, im Editor bauen, speichern und wieder öffnen, mit Bildern. Vorschau auf Desktop und Mobil und eine Testmail an den Admin funktionieren, die Variablen sind dabei ersetzt. CI ist grün.

## Phase 2: Abonnenten, Versand, Abmeldung

### Double-Opt-in

- **Anmeldung:**
  - Formular im Footer: E-Mail plus Hinweistext mit Link zur Datenschutzerklärung
  - Checkbox im Kundenkonto
  - Rate-Limit und Honeypot gegen Bots
- **Bestätigung:**
  - Die Mail enthält einen signierten Link (`URL::temporarySignedRoute`, 48 h).
  - Erst nach dem Klick wird `confirmed_at` gesetzt und die Einwilligung (IP, Zeitpunkt) gespeichert.
- **Abmeldung:**
  - signierter Link in jeder Mail
  - Header `List-Unsubscribe` und `List-Unsubscribe-Post: List-Unsubscribe=One-Click` (RFC 8058, verlangen Gmail und Yahoo)
  - Abmelden geht ohne Login und mit einem Klick
- **Datenschutz:**
  - Die Datenschutzerklärung (`Unternehmen/Datenschutz`) bekommt einen Abschnitt zum Newsletter, der Text kommt vom Betreiber.
  - Kein Tracking (keine Öffnungs- und Klickmessung) im MVP.

### Versand

- **Ablauf:**
  - Admin klickt „Senden“ und bestätigt mit Empfängerzahl.
  - Der Status wird `sending`.
  - Ein Job `PrepareNewsletterDeliveries` legt für jeden bestätigten Abonnenten eine `newsletter_deliveries`-Zeile an.
  - Je Zeile läuft ein `SendNewsletterJob`.
- **Drosselung:** `RateLimited`-Middleware am Job, Rate konfigurierbar (z. B. 60 pro Minute, Limit des SMTP-Anbieters prüfen). Fehlschläge werden mit Backoff wiederholt, danach bekommt die Zeile den Status `failed`.
- **Abschluss:** Sind alle Zeilen erledigt, wird der Newsletter `sent` mit `sent_at`. Der Admin sieht den Fortschritt (gesendet, offen, fehlgeschlagen).
- **Sandbox:** Im Sandbox-Modus gehen Mails nur an die Testadresse, so wie `ModeMailConfiguration` es schon für den Shop regelt.
- **Betrieb:** Den Cron für `schedule:run` einrichten. In `routes/console.php` kommt der Queue-Worker-Aufruf mit `withoutOverlapping()`. Das in der README dokumentieren.

### Tests (Phase 2)

- Anmelden, bestätigen und abmelden, auch mit abgelaufenem oder manipuliertem Link.
- Doppelte Anmeldung legt keine Dublette an.
- Nur bestätigte und nicht abgemeldete Abonnenten werden beliefert.
- Jeder Abonnent bekommt eine Mail, auch wenn ein Job erneut läuft.
- Die Abmelde-Header stimmen.
- Rate-Limit greift.
- Bei einem Fehlschlag wird die Zeile als `failed` markiert, die übrigen laufen weiter.

## Bewusst nicht im Umfang

A/B-Tests, Öffnungs- und Klick-Tracking, Segmente und Automationen, Vorlagen-Marktplatz, eigener Mailserver und Bounce-Verarbeitung. Bounces landen zunächst im Postfach des SMTP-Absenders.

## Offene Punkte vor dem Start

1. npm-Abhängigkeiten `grapesjs` und `grapesjs-mjml` freigeben.
2. Limits des SMTP-Anbieters für Massenversand klären (Mails pro Stunde, ggf. eigene Absenderadresse `newsletter@…`).
3. Datenschutztext zum Newsletter vom Betreiber.
4. Cron auf dem Server anlegen (Phase 2).
