# Keine Konfiguration im Admin

Status: accepted (Oktober 2026)

Der Admin pflegt nur Geschäftsdaten (Produkte, Hersteller, Versandarten, Bestellungen, Marketing-Inhalte), aber keine Konfiguration. Kontaktdaten und Bankverbindung stehen in `config/shop.php`, Zugangsdaten (PayPal, SMTP, Admin) in der Env, der Shopname ist `APP_NAME`. Die frühere Seite „Einstellungen → Allgemein“ und die Tabelle `settings` sind entfernt.

Änderungen an diesen Werten laufen über einen Commit und ein Deployment. Das ist bei Werten, die sich fast nie ändern, kein Nachteil; dafür kann niemand im Admin versehentlich die Bestell-Benachrichtigungsadresse leeren, und Standardwerte widersprechen sich nicht mehr.

Telefon und Fax werden als Vorwahl und Rufnummer getrennt gepflegt (`App\Support\PhoneNumber`), weil sich aus reinen Ziffern nicht ablesen lässt, wo die Vorwahl endet. Anzeige („02301 / 188-600“) und `tel:`-Link entstehen daraus automatisch.
