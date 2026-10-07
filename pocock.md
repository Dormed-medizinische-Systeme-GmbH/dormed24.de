# Test-Review

Grundlage: Lesen von `tests/`, `app/` und `routes/` mit den Maßstäben des `tdd`-Skills (Verhalten an öffentlichen Seams testen, keine tautologischen oder implementierungsgekoppelten Tests), ergänzt um die **gemessene Zeilenabdeckung** aus dem CI-Lauf von PR #17.

## Gemessene Abdeckung (CI-Lauf PR #17, Head `17beae1`)

- **Gesamt: 84,8 %** Zeilenabdeckung von `app/`.
- 384 Tests bestanden, 2 übersprungen (die stillgelegten Rating-Tests).
- Gemessen wird nur auf Pull Requests (`tests.yml`, Schritt "Tests with coverage", Ausgabe in der Job-Zusammenfassung). Es gibt noch **keinen Mindestwert**. Das Deploy-Gate auf `main` läuft ohne Messung.
- Zeilenabdeckung zeigt nur, welche Zeilen ausgeführt wurden, nicht ob die Assertions etwas taugen.

### Niedrig oder 0 %

| Datei | Abdeckung | Bemerkung |
|---|---|---|
| `Services/PayPalService` | 3,4 % | In allen Tests gemockt; SDK-Aufrufe und `verifyWebhook` laufen nie |
| `Mail/PaymentConfirmationMail` | 14,3 % | Tests nutzen `Mail::fake()`, das Mailable wird nie gerendert |
| `Mail/PaymentAmountMismatchMail` | 36,4 % | s. o. |
| `Console/Commands/AddUser` | 0 % | |
| `Console/Commands/InstallFeaturesCommand` | 0 % | |
| `SitemapController` | 0 % | |
| `RatingController`, `StoreRatingRequest` | 0 % | Route deaktiviert, Tests stillgelegt |
| `Admin/ManufacturerController` | 55,6 % | |
| `Models/ProductImage` | 66,7 % | `url`-Accessor (Zeile 28) |
| `Models/Order` | 69,2 % | Zeilen 68–77 |
| `Providers/AppServiceProvider` | 73,9 % | |
| `Console/Commands/ShowLegacyCredentials` | 77,3 % | |
| `Admin/ProductController` | 79,3 % | |
| `Support/Orders/OrderManager` | 83,3 % | Fehlerpfade beim Mailversand (Zeilen 100–110, 157–167) |

### Durch diesen PR geschlossen

`Admin/ProductImageController` und `Admin/ShippingMethodController` stehen bei 100 % (vorher ohne Test), `Admin/OrderController` bei 100 %, `PayPalController` bei 92,2 %.

### Korrektur einer früheren Annahme

In der ersten Fassung stand, `AddressRules`, `ProductFilters`, `ProductSort`, `SessionCartStore`, `EnvAdminUserProvider`, `EnsureAdmin`, `HandleInertiaRequests` und `HashAdminPassword` hätten keine Tests. Das war falsch: Die Suche nach dem Klassennamen in `tests/` übersieht indirekte Abdeckung. Gemessen sind diese Klassen zu 87,5 bis 100 % abgedeckt, nur eben über Feature-Tests statt über eigene Unit-Tests. Offen bleibt der Wunsch nach schnellen, datenbankfreien Unit-Tests für reine Logik (`Money`, `ProductFilters`, `ProductSort`, `AddressRules`). `tests/Unit/` ist weiterhin leer.

## Offen: Lücken

- **`PayPalService::verifyWebhook()`:** Der Pfad "keine Webhook-ID" ist getestet (Sandbox und Live). Der Erfolgspfad (`verification_status === 'SUCCESS'`), der Exception-Pfad und die Weitergabe der `PAYPAL-*`-Header sind es nicht. Grund: `client()` erzeugt das SDK intern und holt per Netzwerk ein Token. Ohne injizierbaren Client (Konstruktor oder Factory) lässt sich das nicht deterministisch testen. Das ist die einzige Authentifizierung des Webhooks und der wichtigste verbleibende Punkt.
- **Mail-Rendering:** Je ein Test pro Mailable, der `render()` aufruft. Ein kaputtes Template oder View fällt sonst erst beim echten Versand auf.
- **`PayPalController` (Restlücken):** `createOrder` mit leerem Warenkorb (Zeile 42) oder unvollständiger Adresse (46), PayPal-Antwort ohne `id` (67–71), 409 bei bereits abgeschlossener Zahlung (123), Exception-Pfad in `afterPayment` (242–250), Webhook-Fallbacks (361–365).
- **`OrderManager`:** Fehlerpfade (Bestellung ohne Kunde, Mailversand wirft).
- **`SitemapController`, statische Seiten (FAQ, AGB, Impressum, Datenschutz, Widerruf), `/.well-known/passkey-endpoints`:** Ein Smoke-Test pro Route reicht.
- **Commands:** `AddUser`, `InstallFeaturesCommand` ohne jeden Test.
- **`markPaymentAndOrder()`:** Reihenfolge-Schutz ist umgesetzt (spätes `DENIED` für bezahlte/erstattete Orders wird ignoriert, Teilerstattungen setzen die Order nicht auf `refunded`). Der Name der Methode ist weiterhin unklar.
- **Weitere PayPal-Befunde aus dem Review, nicht umgesetzt:** Capture-Status `PENDING` und Währungsabgleich, `failed` trotz eingezogenem Geld, `cancelStalePendingPayPalOrders`, fehlende Webhook-Events (`PENDING`, `REVERSED`), `createOrder`-Payload (`shipping_preference`, leere `address_line_2`), Idempotenz-Header beim Refund.

## Offen: Schwächen bestehender Tests

- `RatingTest`: zwei Tests sind dauerhaft per `markTestSkipped` stillgelegt und referenzieren die auskommentierte Route `ratings.store`. Toter Testbestand. Entweder Route reaktivieren oder die Tests löschen.
- Fortify-Tests skippen sich, wenn das Feature in `config/fortify.php` fehlt. Aktuell sind alle aktiv (nur die 2 Rating-Tests werden übersprungen). Wird ein Feature abgeschaltet, fällt die Abdeckung still weg.
- `DeployCheckTest` ist laut eigenem Kommentar temporär. Zusammen mit der Route `/deploy-check` entfernen, sobald der Deploy verifiziert ist.
- Viele Tests prüfen nur den Statuscode (`assertOk`), vor allem `ProductBrowsingTest` und `CategoryPageTest`. Ob die richtigen Produkte, Sortierung und Filter ankommen, bleibt offen. Nachziehen mit `assertInertia(...)` auf Props.
- Debug-Route `/test` (`test.hover-header`) in `routes/web.php`: öffentlich erreichbar, laut Kommentar zum Entfernen vorgesehen.
- Die PayPal-Tests mocken `PayPalService` vollständig. Ob die Mock-Rückgaben der echten SDK-Antwort entsprechen (Struktur von `purchase_units[0].payments.captures[0]`), ist nirgends gegen echte Beispielantworten abgesichert. Empfehlung: ein oder zwei aufgezeichnete Sandbox-Antworten als Fixture.
- Annahme, nicht gegen das SDK geprüft: `blendbyte/paypal` meldet API-Fehler als Array statt als Exception. Der Refund-Fix ist in beiden Fällen richtig.

## Offen: Frontend

Es gibt keine Frontend-Tests (weder vitest noch playwright). `package.json` enthält nur `svelte-check`, ESLint und Prettier. Der komplette Checkout läuft über Svelte/Inertia, inklusive PayPal-Buttons. Mindestens ein End-to-End-Test für Warenkorb → Checkout wäre sinnvoll. Das ist eine Abhängigkeitsentscheidung (neue Dev-Dependency) und braucht eine Freigabe. Die Backend-Abdeckung oben sagt darüber nichts.

## Nicht beurteilt

- Laufzeit und Stabilität der Suite (34 s im CI-Lauf mit Coverage).
- Ob ein Mindestwert für die Abdeckung sinnvoll ist. Mein Vorschlag: erst ein paar Läufe beobachten, dann den Wert knapp unter dem Ist-Stand setzen (z. B. `--min=80`).
