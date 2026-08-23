# Netto/Brutto-Fix — mein aktuelles Verständnis

Stand: 31.07.2026, vor Beginn der Umsetzung. Diese Datei ist der
Abgleich-Zwischenstand, kein Audit-Report wie ANALYSE-\*.md.

## Gemeldeter Fehler

> Die eingegebenen Preise im Admin sind NETTO und im Checkout sollte dann
> auf die gesamte Summe die MwSt + Versand draufgerechnet werden. Dies
> muss serverseitig validiert werden und die Tests müssen angepasst
> werden.

## Aktueller (fehlerhafter) Stand

`CartService::cart()` (`app/Support/Cart/CartService.php:53-58`) rechnet
**rückwärts**, als wären Produkt- und Versandpreis bereits Brutto:

```php
$subtotalCents = $items->sum('line_total_cents');      // Summe roher product.price
$shippingCents = (int) ($selectedShipping['price_cents'] ?? 0);
$totalCents = $subtotalCents + $shippingCents;          // wird als "Brutto" behandelt
$netTotalCents = (int) round($totalCents / (1 + ($vatRate / 100)));  // MwSt. wird HERAUSgerechnet
$vatAmountCents = $totalCents - $netTotalCents;
```

Da die Admin-Preise tatsächlich netto sind, ist das falsch: es wird MwSt.
aus einer Summe herausgerechnet, die selbst schon zu niedrig ist (Netto
statt Brutto) — der Kunde zahlt zu wenig, der ausgewiesene MwSt.-Betrag ist
zu niedrig.

**Reicht weiter als nur der Checkout-Total:** `ProductCard.svelte` und
`Products/Show.svelte` zeigen `product.price` **direkt und unverändert**
an, mit einer Fußnote „\*Preise inkl. MwSt.". Diese Seiten laufen aber gar
nicht über den `CartService` — der Preis kommt roh vom `ProductController`
(`array_merge($product->toArray(), ...)`) bzw. `CategoryController`. Schon
auf der Produktliste/-Detailseite wird also aktuell der Netto-Preis fälschlich
als „inkl. MwSt." ausgewiesen.

`OrderManager::summaryFromOrder()` (`app/Support/Orders/OrderManager.php:193-212`)
nutzt dieselbe Rückwärts-Formel, aber auf einen **bereits gespeicherten**
`order.total_amount`. Das ist rechnerisch **nicht** falsch — sobald
`total_amount` (kommt aus `cart['total']` zum Zeitpunkt der Bestellung)
durch den CartService-Fix korrekt brutto ist, liefert die
Rückwärts-Extraktion aus einem echten Brutto-Betrag ein korrektes
Netto/MwSt.-Split. **Diese Stelle muss ich also nicht anfassen.**

## Bestätigte Entscheidungen (aus Rückfragen)

1. **Versandpreis ist ebenfalls netto.** MwSt. wird auf
   `Produktsumme_netto + Versand_netto` zusammen berechnet, nicht nur auf
   die Produkte.
2. **Anzeige-Scope: überall.** Jeder kundensichtbare Preis (Produktliste,
   Produktdetail, Warenkorb, Checkout) zeigt künftig **Brutto**. Der
   Admin trägt weiterhin **netto** ein — nur die Anzeige/Berechnung ändert
   sich, nicht die gespeicherten Werte in `products.price` /
   `product_variants.price` / `shipping_methods.price`.

## Zielformel

```
netTotal   = subtotal_netto (Summe Produktpreise × Menge) + shipping_netto
vatAmount  = round(netTotal × vatRate / 100)
grossTotal = netTotal + vatAmount        // das, was der Kunde zahlt / PayPal belastet wird
```

Pro Produkt/Variante für die Anzeige (Produktliste, -Detail, Warenkorb-Zeilen):

```
unitPriceGross = round(unitPriceNet × (1 + vatRate / 100))
```

## Betroffene Stellen (serverseitig)

- **`app/Support/Cart/CartService.php`**
  - `items()`: `unitPriceCents` muss von netto auf brutto umgerechnet
    werden, direkt an der Quelle (`variant->price ?? product->price`) —
    dadurch sind `unit_price`, `line_total`, `line_total_cents` und in
    Folge `subtotal` automatisch brutto.
  - `cart()`: Formel umdrehen wie oben (netTotal zuerst, dann MwSt.
    addieren statt herausrechnen). `shippingCents` muss vor der
    MwSt.-Berechnung ebenfalls als netto behandelt werden (aus
    Entscheidung 1).
- **`app/Http/Controllers/ProductController.php`** (`index`, `show`,
  vermutlich auch `search`) — Preis/Varianten-Preise vor dem Rendern an
  die Inertia-Page auf brutto umrechnen.
- **`app/Http/Controllers/CategoryController.php`** (`show`) — gleiche
  Umrechnung für die Kategorie-Produktliste.
- Vermutlich sinnvoll: eine kleine gemeinsame Helper-Funktion/-Klasse für
  „netto-Cents → brutto-Cents" (z. B. unter `App\Support\Money` oder
  direkt neben `config('shop.cart.vat_rate')`), damit die Umrechnung nicht
  dreifach dupliziert wird (CartService, ProductController,
  CategoryController).
- **`OrderManager`**: nach aktuellem Verständnis **keine Änderung nötig**
  (Begründung oben) — werde ich aber beim Umsetzen nochmal gegen die
  tatsächlichen Zahlen verifizieren, nicht nur gegen die Formel.
- **PayPal-Betrag** (`PayPalService::createOrder()` bekommt
  `cart['total']`): sollte automatisch korrekt sein, sobald
  `cart['total']` echt brutto ist — keine eigene Änderung erwartet, wird
  aber mitgetestet.

## Nicht Teil dieses Fixes (Annahme, sofern nicht widersprochen)

- Die gespeicherten Werte in `products.price`, `product_variants.price`,
  `shipping_methods.price` bleiben **netto** — es wird keine Migration
  gemacht, die Bestandsdaten umrechnet.
- Admin-Formularfelder (`Preis (€)` in Products/Create.svelte,
  Products/Edit.svelte, Admin/Settings/Shipping.svelte) bekommen aktuell
  **keine Label-Änderung** zu „Preis (netto)" — das wäre aus meiner Sicht
  eine sinnvolle Klarstellung, ist aber nicht explizit beauftragt. Sag
  Bescheid, falls das mit rein soll.
- Admin-Dashboard-Umsatzanzeige (`Admin/Dashboard.svelte`, summiert
  `orders.total_amount` für paid-Orders) bleibt unverändert — zeigt
  weiterhin Brutto-Umsatz, wie schon bisher (war kein gemeldeter Fehler).

## Betroffene Tests (bekannt, werden beim Umsetzen angepasst)

Gefunden per Grep auf `vat_rate|net_total|vat_amount|subtotal|line_total|unit_price`:

- `tests/Feature/Cart/CartFlowTest.php`
- `tests/Feature/Checkout/CheckoutFlowTest.php`
- `tests/Feature/Checkout/PayPalCaptureTest.php`
- `tests/Feature/Orders/OrderManagerTest.php`
- `tests/Feature/Customer/CustomerOrdersTest.php`
- `tests/Feature/Admin/ProductEditTest.php`
- `tests/Feature/Settings/ProfileUpdateTest.php` (vermutlich nur
  zufälliger Treffer, noch nicht inhaltlich geprüft)

Alle Tests, die aktuell `total = subtotal + shipping` mit unveränderten
Produktpreisen erwarten, werden auf die neue Brutto-Formel umgestellt.
Zusätzlich neue Testfälle für: Produktlisten-/Detailseite zeigt Brutto,
nicht Netto; Versand fließt netto in die MwSt.-Berechnung ein.

## Was noch fehlt, bevor ich anfange

Ich hab die Formel und den Scope jetzt klar — bevor ich in die
Umsetzung gehe, wollte ich diesen Stand einmal schriftlich fixieren,
damit wir auf derselben Seite sind. Sag Bescheid, wenn das so passt,
dann lege ich los (CartService zuerst, dann Controller, dann Tests).
