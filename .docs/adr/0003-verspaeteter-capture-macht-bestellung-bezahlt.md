# Verspäteter Capture macht die Bestellung bezahlt

Status: accepted (Oktober 2026)

PayPal liefert Webhooks mindestens einmal und unsortiert, und ein Kunde kann eine Zahlung abbrechen, die PayPal trotzdem noch einzieht. Kommt Geld für eine Bestellung an, die bereits `failed` oder `cancelled` ist, wird sie **bezahlt** (`paid`), weil der Status zum eingezogenen Geld passen muss. Die Alternative, den Endzustand zu behalten und das Geld automatisch zu erstatten, hätte Kunden, die tatsächlich bezahlt haben, ohne Ware gelassen und eine ungewollte Erstattung ausgelöst.

Endgültig ist nur `refunded`: Ein spät zugestelltes `CAPTURE.COMPLETED` nach einer Erstattung ändert nichts mehr. `paid` kann nur noch zu `refunded` werden.
