# dormed24

Online-Shop für Medizintechnik (Ultraschall, Praxisbedarf) der Dormed medizinische Systeme GmbH, mit Admin für Bestellungen, Katalog und Marketing.

## Bestellung und Zahlung

**Bestellung**:
Ein abgeschickter Warenkorb eines Kunden mit festgeschriebenen Preisen, Adressen und Bezahlart.
_Avoid_: Order (im Text), Auftrag, Kauf

**Bestellstatus**:
Der Stand einer Bestellung aus Sicht des Shops: offen, bezahlt, fehlgeschlagen, storniert oder erstattet. Es gibt bewusst keinen Versand- oder Bearbeitungsstatus.
_Avoid_: Fulfillment-Status, Auftragsstatus

**Bezahlart**:
Der Weg, auf dem eine Bestellung bezahlt wird: Rechnung, PayPal, Lastschrift oder Kreditkarte (die letzten drei laufen über PayPal).
_Avoid_: Zahlungsmethode, Zahlungsanbieter

**Zahlung**:
Ein Zahlungsvorgang zu einer Bestellung bei PayPal, vom Anlegen bis zum Einzug oder zur Erstattung.
_Avoid_: Transaktion, Payment (im Text)

**Zahlungsstatus**:
Der Stand einer Zahlung, so wie PayPal ihn meldet oder der Shop ihn ableitet (angelegt, freigegeben, in Prüfung, eingezogen, fehlgeschlagen, abweichend, erstattet, zurückgebucht).

**Capture**:
Der Einzug des Geldes bei PayPal, nachdem der Käufer zugestimmt hat.
_Avoid_: Abbuchung

**Abweichung**:
Eine Capture, deren Betrag oder Währung nicht zur Bestellung passt. Das Geld ist eingezogen, die Bestellung wird nicht erfüllt.
_Avoid_: Mismatch (im Text)

**Erstattung**:
Die Rückzahlung des vollen eingezogenen Betrags an den Käufer, ausgelöst im Admin oder bei PayPal.
_Avoid_: Refund (im Text), Gutschrift

**Rückbuchung**:
Eine Rückzahlung, die PayPal selbst auslöst (z. B. nach einem Konflikt). Für den Shop wirkt sie wie eine Erstattung.
_Avoid_: Reversal (im Text)

**Zahlungsdienst**:
Der externe Dienst, der eine Bezahlart abwickelt; heute nur PayPal. Im Code über den Seam `PaymentGateway` angebunden.
_Avoid_: Payment-Provider (im Text)

**Zahlungsabgleich**:
Das Abgleichen dessen, was der Zahlungsdienst meldet (Capture, Erstattung, Webhook), mit der Bestellung: Betrag und Währung prüfen, Zahlungsstatus setzen, Bestellstatus weiterschalten.
_Avoid_: Reconciliation (im Text), Payment-Handling

**Zahlungseingang bestätigen**:
Die einzige manuelle Statusänderung im Admin: Eine Rechnungsbestellung wird nach eingegangener Überweisung bezahlt.

## Katalog und Warenkorb

**Warenkorb**:
Die Auswahl eines Besuchers vor der Bestellung, mit live gelesenen Preisen.
_Avoid_: Cart (im Text), Einkaufswagen

**Kategorie**:
Ein fester Bereich des Katalogs (z. B. Ultraschallsysteme), im Code konfiguriert, nicht im Admin pflegbar.

**Fachrichtung**:
Eine medizinische Fachrichtung, der Produkte zugeordnet werden, um sie für Praxen filterbar zu machen.
_Avoid_: Spezialität

## Marketing

**Newsletter**:
Eine Mail an Abonnenten, im Editor gestaltet. Bisher nur als Entwurf mit Testmail an die Shop-Adresse.

**Social-Beitrag**:
Ein geplanter Text für LinkedIn und/oder Facebook. Veröffentlicht wird noch nicht.
_Avoid_: Post, Social-Post (im Text)

**Magazin-Artikel**:
Ein redaktioneller Beitrag im Magazin des Shops (Ratgeber, Aktuelles, Praxiswissen).
_Avoid_: Blogpost, Blog

**Kanal**:
Eine Plattform, auf der Marketing-Inhalte erscheinen (LinkedIn, Facebook, Newsletter).

## Betrieb

**Sandbox-Modus / Live-Modus**:
Der Betriebsmodus, der PayPal-Zugang, Mailversand und Sandbox-Banner wählt. Live nur in Produktion ohne Debug.
_Avoid_: Testmodus
