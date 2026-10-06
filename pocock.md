# Test-Review (Stand: statische Prüfung, nicht ausgeführt)

Grundlage: Lesen von `tests/`, `app/` und `routes/` mit den Maßstäben des `tdd`-Skills (Verhalten an öffentlichen Seams testen, keine tautologischen oder implementierungsgekoppelten Tests). Die Suite wurde in dieser Session nicht ausgeführt; laut Auftraggeber ist sie grün.

Bereits umgesetzt (Branch `tests/paypal-images-shipping-gaps`): Webhook-Verifikation, Webhook-Handler, `ProductImageController`, `ShippingMethodController`, `CartServiceQueryCountTest`. Siehe unten die Liste der **offenen** Punkte.

## Offen: Lücken

### Ohne Test
- `SitemapController` (`/sitemap.xml`), statische Seiten (FAQ, AGB, Impressum, Datenschutz, Widerruf) und `/.well-known/passkey-endpoints`. Ein Smoke-Test pro Route reicht (Status, Inertia-Komponente bzw. XML-Wohlgeformtheit).
- Support-Klassen: `AddressRules`, `ProductFilters`, `ProductSort`, `SessionCartStore`, `EnvAdminUserProvider`, `EnsureAdmin`, `HandleInertiaRequests`.
- Artisan-Commands: `AddUser`, `HashAdminPassword`, `ShowLegacyCredentials`, `InstallFeaturesCommand`.
- `tests/Unit/` ist leer. Reine Logik (`Money`, `ProductFilters`, `ProductSort`, `AddressRules`) lässt sich ohne Datenbank als Unit-Test prüfen und wäre schneller.

### Nur teilweise abgedeckt
- `PayPalService::verifyWebhook()`: Der Pfad "keine Webhook-ID" ist jetzt getestet (Sandbox und Live). Der Erfolgspfad (`verification_status === 'SUCCESS'`), der Exception-Pfad und die Weitergabe der `PAYPAL-*`-Header sind es **nicht**. Grund: `PayPalService::client()` erzeugt das SDK intern (`new PayPal(...)`) und holt per Netzwerk ein Token. Ohne injizierbaren Client lässt sich das nicht deterministisch testen. Kleines Refactoring nötig (Client per Konstruktor oder Factory injizieren). Das ist die einzige Authentifizierung des Webhooks und der wichtigste verbleibende Punkt.
- ~~`markPaymentAndOrder()` ohne Reihenfolge-Schutz~~ umgesetzt: spätes `DENIED` für bezahlte/erstattete Orders wird ignoriert, Teilerstattungen per Webhook setzen die Order nicht mehr auf `refunded` (siehe PayPal-Review, Befund 3). Offen bleibt: `markPaymentAndOrder` ist als Name weiterhin unklar.

## Offen: Schwächen bestehender Tests

- `RatingTest`: zwei Tests sind dauerhaft per `markTestSkipped` stillgelegt und referenzieren die auskommentierte Route `ratings.store`. Toter Testbestand, ein grüner Lauf täuscht Abdeckung vor. Entweder Route reaktivieren oder die Tests löschen.
- Fortify-Tests (`AuthenticationTest`, `PasswordResetTest`, `EmailVerificationTest`, `TwoFactorChallengeTest`, `RegistrationTest`, `VerificationNotificationTest`, `SecurityTest`) skippen sich, wenn das Feature in `config/fortify.php` fehlt. Aktuell sind alle aktiv. Wird eines abgeschaltet, fällt die Abdeckung still weg und CI zeigt nur "skipped". Besser: Feature als Voraussetzung per Test absichern.
- `DeployCheckTest` ist laut eigenem Kommentar temporär. Zusammen mit der Route `/deploy-check` entfernen, sobald der Deploy verifiziert ist.
- Viele Tests prüfen nur den Statuscode (`assertOk`), vor allem `ProductBrowsingTest` (30 Treffer) und `CategoryPageTest` (11). Ob die richtigen Produkte, Sortierung und Filter ankommen, bleibt offen. Nachziehen mit `assertInertia(...)` auf Props.
- Debug-Route `/test` (`test.hover-header`) in `routes/web.php`: öffentlich erreichbar, ungetestet, laut Kommentar zum Entfernen vorgesehen.
- Fehlende Prüfung auf Kaputt-Konfiguration: die PayPal-Tests mocken `PayPalService` vollständig. Ob die Mock-Rückgaben der echten SDK-Antwort entsprechen (Struktur von `purchase_units[0].payments.captures[0]`), ist nirgends gegen echte Beispielantworten abgesichert. Empfehlung: ein oder zwei aufgezeichnete Sandbox-Antworten als Fixture verwenden.

## Offen: Frontend
Es gibt keine Frontend-Tests (weder vitest noch playwright). `package.json` enthält nur `svelte-check`, ESLint und Prettier. Der komplette Checkout läuft über Svelte/Inertia, inklusive PayPal-Buttons. Mindestens ein End-to-End-Test für Warenkorb → Checkout wäre sinnvoll. Das ist eine Abhängigkeitsentscheidung (neue Dev-Dependency) und braucht eine Freigabe.

## Nicht beurteilt
- Testabdeckung in Prozent (dafür wäre ein Lauf mit Xdebug oder PCOV nötig).
- Laufzeit und Stabilität der Suite.
- Die neu geschriebenen Tests wurden **nicht ausgeführt** (Umgebung blockiert `composer install`, siehe Netzwerk-Policy). Vor dem Merge einmal `php artisan test` und `vendor/bin/pint --dirty` laufen lassen.
