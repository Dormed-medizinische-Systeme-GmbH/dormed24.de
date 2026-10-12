# Analyse dormed-shop: offene Punkte

Stand 07.10.2026. Zusammengeführt aus ANALYSE-V1 bis V4 (Juli 2026) und dem
Test-Review (früher `pocock.md`). Was dort eindeutig und vollständig erledigt
war, ist entfernt. Jeder Punkt unten wurde gegen den aktuellen Code geprüft. In
Klammern steht, woher er stammt.

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
Der Button „Kaufen auf Rechnung“ sperrt sich nicht gegen einen zweiten Klick, solange die Anfrage läuft. Abhilfe:
`Cache::lock` um den Submit oder ein Idempotency-Key aus der Session.

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

### PHPStan-Baseline: 2 Einträge, beide bewusst

Übrig sind nur die `$this->command?->`-Aufrufe in `ProductSeeder` und
`RatingSeeder`. Laravel typisiert `$command` als nie null, beim direkten
Aufruf eines Seeders (z. B. `ProductSeederTest`) ist es aber null. Die
Null-Sicherung bleibt deshalb.

## 🚀 Betrieb / Launch

1. **DB-Backup-Strategie**: seit V2 offen, vor dem Launch entscheiden.
   Einfachster Start: nächtliches Dump-Cron plus Offsite-Kopie, zusammen mit
   `storage/app/public`.
2. **PayPal-Webhook-IDs** in der Env setzen (`PAYPAL_SANDBOX_WEBHOOK_ID` /
   `PAYPAL_LIVE_WEBHOOK_ID`). Ohne sie lehnt `verifyWebhook()` alle Webhooks
   ab, sichtbar nur im Log. Bis Oktober 2026 scheiterte die Prüfung auch mit
   gesetzter ID (falscher SDK-Aufruf), Webhooks kamen also nie durch. Seit
   der Umstellung auf `verifyWebHookLocally()` funktioniert sie.
3. **Katalog-Erstimport** in Produktion nur bewusst über
   `SEED_PRODUCTS_FORCE=true php artisan db:seed --class=ProductSeeder`, danach
   das Flag wieder entfernen.
4. `php artisan storage:link`, `APP_ENV=production`, `APP_DEBUG=false`,
   `SESSION_SECURE_COOKIE=true`, `config:cache`/`route:cache`.
5. Node ≥ 20.19 auf dem Build-System (`engines` und `.nvmrc` sind im Repo).

## 🧪 Tests

Abdeckung laut CI-Lauf von PR #21: **89,7 %** von `app/`, 423 PHP-Tests, dazu
107 Vitest-Tests. Gemessen wird nur auf Pull Requests (`tests.yml`, Schritt
„Tests with coverage“), ohne Mindestwert. Vorschlag: `--min=85`.

### Lücken

| Datei                          | Abdeckung | Was fehlt                                                         |
| ------------------------------ | --------- | ----------------------------------------------------------------- |
| `Providers/AppServiceProvider` | 73,9 %    | Passwortregeln in Produktion (min. 12, gemischt, `uncompromised`) |

### Sonstiges

- **Unit-Tests für `ProductFilters`:** `ProductSort`, `AddressRules` und
  `EnvAdmin` haben Unit-Tests, `Money` ist in `CartValueObjectsTest` abgedeckt.
  `ProductFilters` läuft nur über die Feature-Tests der Produktliste.
- **Fortify-Tests** skippen sich, wenn ein Feature in `config/fortify.php`
  fehlt. Aktuell sind alle aktiv; wird eines abgeschaltet, fällt die Abdeckung
  still weg.
- **Manueller PayPal-Sandbox-Browsertest** (seit V2 offen, seit V4 Pflicht):
  CSRF ist in PHPUnit deaktiviert, der Button-Flow lässt sich serverseitig nicht
  testen. Dabei auch einen echten Webhook prüfen, z. B. eine Erstattung im
  PayPal-Dashboard: Die Bestellung muss danach auf `refunded` stehen.
- **End-to-End je Bezahlweg** (V1): PayPal ist mit
  `PayPalPaymentLifecycleTest` serverseitig durchgehend getestet (anlegen,
  bezahlen, erstatten), aber ohne Mail-Assertions. Für Rechnung fehlt ein
  durchgehender Test. Ein Browser-Test (Warenkorb → Checkout) bräuchte
  Playwright, eine Abhängigkeitsentscheidung.

## 📌 Bewusste Entscheidungen (keine Aufgaben, nicht erneut melden)

- Kein Bestandskonzept, nur `is_available`, Menge frei bis 99.
- Cookie-Banner: „Nur notwendige“ und „Alle erlauben“ tun dasselbe. Das ist in
  Ordnung, solange nur technisch notwendige Cookies gesetzt werden. Vor
  Analytics oder Drittdiensten braucht es echtes Consent-Management.
- Ungenutzte shadcn-Kit-Dateien unter `components/ui/` bleiben (`add --all`).
- `ShopHeader-with-hover.svelte` bleibt als Backup.
- Die Test-Route `/test` (`test.hover-header`, Landingpage mit Hover-Header)
  bleibt vorerst öffentlich erreichbar.
- Mails gehen synchron raus, ohne Queue-Worker.
- Detailseiten nicht verfügbarer Produkte bleiben online, die Sitemap listet
  alle Produkte.
- Dashboard-Umsatz ohne `is_test`-Filter.
- PayPal-Zahlungen in Prüfung (`PENDING`) zeigen dem Kunden einen Hinweis
  statt der Erfolgsseite; die Bestätigung kommt per Webhook und Mail.
- Rechnungs-Orders bleiben `pending`, ohne Auto-Cleanup. Verwaiste
  PayPal-Orders anderer Kunden bleiben stehen.
- Inhaltliche Überschneidung `/informationen/zahlung` und
  `/informationen/versand` bleibt vorerst.
- Cart als Shared Prop (Header-Zähler) wird nicht lazy/deferred geladen.
- Keine atomaren Deploys.
- `sessions.user_id` bleibt so benannt. Laravels `DatabaseSessionHandler`
  schreibt fest in diese Spalte, der Inhalt ist die `customers.id` des
  `web`-Guards.
