# Test-Review

Grundlage: Lesen von `tests/`, `app/` und `routes/` mit den Maßstäben des `tdd`-Skills (Verhalten an öffentlichen Seams testen, keine tautologischen oder implementierungsgekoppelten Tests), ergänzt um die **gemessene Zeilenabdeckung** aus der CI. Erledigte Punkte sind entfernt; die Historie steht in den PRs.

## Gemessene Abdeckung (CI-Lauf PR #21, Head `2fb71d6`)

- **Gesamt: 89,7 %** Zeilenabdeckung von `app/` (vorher 84,8 % bei PR #17).
- 423 Tests bestanden, 2 übersprungen. Die übersprungenen sind die stillgelegten Rating-Tests; mit der Reaktivierung der Bewertungen entfällt das.
- Frontend: 107 Vitest-Tests (`npm run test`), ohne Abdeckungsmessung.
- Gemessen wird nur auf Pull Requests (`tests.yml`, Schritt "Tests with coverage", Ausgabe im Job-Log). Es gibt noch **keinen Mindestwert**. Das Deploy-Gate auf `main` läuft ohne Messung.
- Zeilenabdeckung zeigt nur, welche Zeilen ausgeführt wurden, nicht ob die Assertions etwas taugen.

### Niedrig oder 0 %

| Datei | Abdeckung | Bemerkung |
|---|---|---|
| `Mail/PaymentConfirmationMail` | 14,3 % | Tests nutzen `Mail::fake()`, das Mailable wird nie gerendert |
| `Mail/PaymentAmountMismatchMail` | 36,4 % | s. o. |
| `Console/Commands/AddUser` | 0 % | |
| `Console/Commands/InstallFeaturesCommand` | 0 % | |
| `SitemapController` | 0 % | |
| `Admin/ManufacturerController` | 55,6 % | |
| `Auth/EnvAdmin` | 61,5 % | Remember-Token und Serialisierung (`toArray`, `jsonSerialize`) |
| `Models/ProductImage` | 66,7 % | `url`-Accessor (Zeile 28) |
| `Models/Order` | 69,2 % | Zeilen 68–77 |
| `Providers/AppServiceProvider` | 73,9 % | |
| `Console/Commands/ShowLegacyCredentials` | 77,3 % | |
| `Admin/ProductController` | 79,3 % | |
| `Support/Orders/OrderManager` | 83,3 % | Fehlerpfade beim Mailversand (Zeilen 100–110, 157–167) |

`Services/PayPalService` steht inzwischen bei 93,1 % (vorher 3,4 %): Der echte Service läuft über `PayPalClientFactory` gegen spezifikationsgeprüfte Fixtures. Offen sind nur ein fehlgeschlagener Access-Token (Zeile 47), der Exception-Pfad der Webhook-Prüfung und `showOrderDetails()`. `RatingController` und `StoreRatingRequest` (bei PR #21 noch 0 %) deckt der neue `RatingTest` ab.

## Offen: Lücken

- **Mail-Rendering:** Je ein Test pro Mailable, der `render()` aufruft. Ein kaputtes Template oder View fällt sonst erst beim echten Versand auf.
- **`PayPalController` (92,4 %, Restlücken):** `createOrder` mit leerem Warenkorb (400) oder unvollständiger Adresse (422), PayPal-Antwort ohne `id`, 409 bei bereits abgeschlossener Zahlung, Exception-Pfad in `afterPayment`, Mailversand-Fehler beim Betragsabweichungs-Alarm.
- **`PayPalService` (Restlücken):** s. o.
- **`OrderManager`:** Fehlerpfade (Bestellung ohne Kunde, Mailversand wirft).
- **`SitemapController`, statische Seiten (FAQ, AGB, Impressum, Datenschutz, Widerruf), `/.well-known/passkey-endpoints`:** Ein Smoke-Test pro Route reicht.
- **Commands:** `AddUser`, `InstallFeaturesCommand` ohne jeden Test.
- **Unit-Tests für reine Logik:** `Money`, `ProductFilters`, `ProductSort` und `AddressRules` sind über Feature-Tests zu 87,5–100 % abgedeckt, aber nicht datenbankfrei getestet. In `tests/Unit/` liegt bisher nur `PayPalFixturesTest`.
- **`markPaymentAndOrder()`:** Der Name sagt nicht, was die Methode tut (Status von Zahlung und Bestellung aus einem Webhook übernehmen, mit Reihenfolge-Schutz).
- **Weitere PayPal-Befunde aus dem Review, nicht umgesetzt:** Währungsabgleich beim Capture, `failed` trotz eingezogenem Geld, `cancelStalePendingPayPalOrders`, fehlende Webhook-Events (`PENDING`, `REVERSED`), `createOrder`-Payload (`shipping_preference`, leere `address_line_2`), Idempotenz-Header (`PayPal-Request-Id`) beim Refund.

## Offen: Schwächen bestehender Tests

- Fortify-Tests skippen sich, wenn das Feature in `config/fortify.php` fehlt. Aktuell sind alle aktiv. Wird ein Feature abgeschaltet, fällt die Abdeckung still weg.
- `DeployCheckTest` ist laut eigenem Kommentar temporär. Zusammen mit der Route `/deploy-check` entfernen, sobald der Deploy verifiziert ist.
- Debug-Route `/test` (`test.hover-header`) in `routes/web.php`: öffentlich erreichbar, laut Kommentar zum Entfernen vorgesehen.

## Offen: Frontend

- **End-to-End im Browser.** Ein Test für Warenkorb → Checkout fehlt. jsdom ersetzt keinen echten Browser und keine echte PayPal-Sandbox. Dafür wäre Playwright nötig, eine weitere Abhängigkeitsentscheidung.

## Nicht beurteilt

- Laufzeit und Stabilität der Suite (40 s im CI-Lauf mit Coverage).
- Ob ein Mindestwert für die Abdeckung sinnvoll ist. Mein Vorschlag: den Wert knapp unter dem Ist-Stand setzen (z. B. `--min=85`).
