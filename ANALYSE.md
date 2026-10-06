# Analyse dormed-shop: offene Punkte

Stand 06.10.2026. Zusammengeführt aus ANALYSE-V1 bis V4 (Juli 2026). Was dort
eindeutig und vollständig erledigt war, ist entfernt. Jeder Punkt unten wurde
gegen den aktuellen Code geprüft. In Klammern steht, woher er stammt.

## 🟠 Sicherheit und Abhängigkeiten

### npm audit: 12 Funde, davon 9 hoch (V4 Q-4, verschärft)

Im Juli waren es noch 8 „low“. Aktuell meldet `npm audit` 1 low, 2 moderate
und 9 high:

- `@sveltejs/kit`, `devalue`, `cookie`: kommen über die ungenutzten
  shadcn-Kit-Peer-Deps (`sveltekit-superforms`, `formsnap`).
- `concurrently`: direkte Dev-Abhängigkeit, über `shell-quote`.
- `postcss`, `postcss-selector-parser`, `source-map-js`, `nanoid`,
  `brace-expansion`, `js-yaml`, `joi`: Build- und Tooling-Kette.

Ausgeliefert wird davon nichts (Build-Tooling bzw. tree-shaken). Trotzdem
sollte man `npm audit fix` versuchen und den Rest beim nächsten Kit-Update
mitnehmen.

## 🟡 Businesslogik

### Doppel-Submit kann zwei Rechnungs-Orders erzeugen (V4 B-4)

`CheckoutController::submit()` ist nicht idempotent. Zwei parallele Requests
lesen beide einen gefüllten Warenkorb und legen zwei Orders an. Der
Hold-to-confirm-Button macht das unwahrscheinlich. Abhilfe bei Bedarf:
`Cache::lock` um den Submit oder ein Idempotency-Key aus der Session.

### Bewertungen wieder freischalten, bestellbasiert (V1 #9 → V3 S-1)

Route `POST /products/{product}/ratings` und das Formular auf der Produktseite
sind seit Juli bewusst deaktiviert (Spam-Fläche). Beides soll gemeinsam
zurückkommen, sobald Bewertungen an eine Bestellung gebunden sind.
`RatingController` und `StoreRatingRequest` sind noch da, die zwei
Submission-Tests in `RatingTest` sind `markTestSkipped`.

## 🔍 SEO

### Produkt-Cover werden unskaliert ausgeliefert (V4 SEO-3, zurückgestellt)

`storage/app/public/products/` enthält Originale aus dem Shopware-Import, etliche
über 300 KB. Die Listen rendern sie als Karten-Thumbnails, das drückt LCP und
mobiles Datenvolumen.

- Kleine Lösung: Einmal-Skript, das die Cover auf max. ~800 px WebP verkleinert,
  plus Resize beim Admin-Upload. Braucht eine neue Abhängigkeit, z. B.
  `intervention/image`, die freigegeben werden muss.
- Große Lösung: Thumbnail-Pipeline mit `srcset`.

## 🔵 Code und Doku

### PayPal-Antworten untypisiert (aus V1 #18 / V4 Q-3 abgeleitet)

Die PHPStan-Baseline hat noch 63 Einträge (Juli: 98). Der größte Block sind
17 in `PayPalService`. Ursache ist die PayPal-Bibliothek: Ihre Methoden liefern
je nach Antwort ein Array, einen `StreamInterface` oder einen String. Dazu
kommen 5 im `PayPalController`, meist untypisierte Webhook-Payloads. Abhilfe:
ein kleiner Helfer, der jede Antwort in ein typisiertes Array normalisiert,
plus Array-Shapes für die Webhook-Ressourcen. Der Warenkorb ist seit dem
Cart-DTO (`App\Support\Cart\Cart`) erledigt.

## 🚀 Betrieb / Launch

1. **DB-Backup-Strategie**: seit V2 offen, vor dem Launch entscheiden.
   Einfachster Start: nächtliches Dump-Cron plus Offsite-Kopie, zusammen mit
   `storage/app/public`.
2. **PayPal-Webhook-IDs** in der Env setzen (`PAYPAL_SANDBOX_WEBHOOK_ID` /
   `PAYPAL_LIVE_WEBHOOK_ID`). Ohne sie lehnt `verifyWebhook()` alle Webhooks
   ab, sichtbar nur im Log.
3. **Katalog-Erstimport** in Produktion nur bewusst über
   `SEED_PRODUCTS_FORCE=true php artisan db:seed --class=ProductSeeder`, danach
   das Flag wieder entfernen.
4. `php artisan storage:link`, `APP_ENV=production`, `APP_DEBUG=false`,
   `SESSION_SECURE_COOKIE=true`, `config:cache`/`route:cache`.
5. Node ≥ 20.19 auf dem Build-System (`engines` und `.nvmrc` sind im Repo).

## 🧪 Tests

- **Manueller PayPal-Sandbox-Browsertest** (seit V2 offen, seit V4 Pflicht):
  CSRF ist in PHPUnit deaktiviert, der Button-Flow lässt sich serverseitig nicht
  testen, und der Fehlerpfad antwortet seit V4 mit 422 statt auf die
  Erfolgsseite zu leiten.
- **End-to-End je Bezahlweg** (V1): Warenkorb → Bestellung → `paid` inklusive
  Mail-Assertions, je einmal für Rechnung und PayPal. Einzelschritte sind
  getestet (Checkout, Capture, Webhook, OrderManager), ein durchgehender Test
  pro Weg ist nicht eindeutig vorhanden.

## 📌 Bewusste Entscheidungen (keine Aufgaben, nicht erneut melden)

- Kein Bestandskonzept, nur `is_available`, Menge frei bis 99.
- Cookie-Banner: „Nur notwendige“ und „Alle erlauben“ tun dasselbe. Das ist in
  Ordnung, solange nur technisch notwendige Cookies gesetzt werden. Vor
  Analytics oder Drittdiensten braucht es echtes Consent-Management.
- Ungenutzte shadcn-Kit-Dateien unter `components/ui/` bleiben (`add --all`).
- `ShopHeader-with-hover.svelte` bleibt als Backup.
- Mails gehen synchron raus, ohne Queue-Worker.
- Detailseiten nicht verfügbarer Produkte bleiben online, die Sitemap listet
  alle Produkte.
- Dashboard-Umsatz ohne `is_test`-Filter.
- Rechnungs-Orders bleiben `pending`, ohne Auto-Cleanup. Verwaiste
  PayPal-Orders anderer Kunden bleiben stehen.
- Inhaltliche Überschneidung `/informationen/zahlung` und
  `/informationen/versand` bleibt vorerst.
- Cart als Shared Prop (Header-Zähler) wird nicht lazy/deferred geladen.
- Keine atomaren Deploys.
- `sessions.user_id` bleibt so benannt. Laravels `DatabaseSessionHandler`
  schreibt fest in diese Spalte, der Inhalt ist die `customers.id` des
  `web`-Guards.
