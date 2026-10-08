# Kein Fulfillment-Status

Status: accepted (übernommen aus AGENTS.md, Betriebsmodell)

Der Shop deckt nur Bestellannahme und Zahlung ab. Rechnung, DPD-Versand und Buchhaltung laufen außerhalb des Systems in der Rechnungssoftware. Deshalb gibt es keinen Bestellstatus wie „in Bearbeitung“, „versendet“ oder „abgeschlossen“; `paid` ist der finale Erfolgszustand. Solche Werte wurden bewusst entfernt und dürfen bei Umbauten am Bestell-Lebenszyklus nicht wieder eingeführt werden.
