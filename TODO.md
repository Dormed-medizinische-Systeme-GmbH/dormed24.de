# TODO — dormed-shop

## IDEEN

- DPD Tracking in App per API einbauen
- Bewertungen order-basiert reaktivieren (Kunde bewertet nur gekaufte Produkte) — Route + Formular sind bis dahin bewusst deaktiviert (ANALYSE-V3 S-1); `RatingController` + Tests liegen als Grundlage bereit

---

## Sicherheit (Backlog aus Code-Audit 2026-06-25)

### 🟢 FormRequest authorize() gibt überall true zurück

**Dateien:** `UpdateProductRequest`, `UpdateCategoryRequest`, `StoreRatingRequest`, etc.
Nur Route-Ebene schützt. Für feinere Permissions später Laravel Policies einführen.

---

## UI/UX — Kundenbereich

- [ ] **Produktfilter fehlen** — `resources/js/pages/Products/Index.svelte`: Nur Sortierung vorhanden. Keine Filter nach Preis, Hersteller, Kategorie oder Rating.

- [ ] **Registrierung** — `resources/js/pages/auth/Register.svelte`: Kein Passwort-Stärke-Indikator, keine AGB-Akzeptanz-Checkbox.

- [ ] **Breadcrumb auf Shop-Seiten** — _teilweise:_ Produkt-Detailseite hat Breadcrumb (Alle Produkte → Kategorie → Produkt); Kategorie-Seite noch nicht.

- [ ] **Bestellzusammenfassung im Checkout** — `resources/js/pages/Checkout/Confirm.svelte`: Produktliste fehlt in der finalen Zusammenfassung (nur Preise sichtbar).

- [ ] **Datenschutz-Versionierung** — `resources/js/pages/Datenschutz.svelte`: Kein Datum / keine Versionsnummer sichtbar (AGB hat inzwischen „Stand: 01.01.2025").

---

## UI/UX — Admin-Bereich (Mitarbeiter)

- [ ] **Bulk-Aktionen ohne Funktion** — `resources/js/pages/Admin/Products/Index.svelte`: Checkboxes für Mehrfachauswahl vorhanden, aber keine Aktion dahinter (kein Bulk-Delete, kein Bulk-Update).

- [ ] **Bestellungen: kein Status-Filter** — `resources/js/pages/Admin/Orders/Index.svelte`: Kein Filter nach Status (Offen / Bezahlt / Storniert), kein Datum-Filter.

- [ ] **Dashboard: nur Basis-Stats** — `resources/js/pages/Admin/Dashboard.svelte`: Nur 2 Charts (Orders + Revenue). Keine Top-Produkte, keine Top-Kunden, keine Conversion-Rate.

- [ ] **Kunden-Filter** — `resources/js/pages/Admin/Customers/Index.svelte`: Nur Name-Suche. Kein Filter nach Verifizierungs-Status oder Registrierungsdatum.

- [ ] **Upload-Fehler nicht angezeigt** — `resources/js/pages/Admin/Products/Edit.svelte`: Wenn Bild-Upload fehlschlägt, gibt es kein visuelles Feedback.

---

Und dann haben wir auch noch diesen Shopheader with hover. Ich meine wir haben auch noch ein Left-Align, der Left-Align ist aber eigentlich hinfällig, der kann komplett weg, aber der hover ist interessant. Ich würde dich bitten eine Slash-Testroute zu machen, die die Frontpage, Landingpage abbildet. Eins zu eins die gleiche Landingpage, bitte ne Kopie daraus machen, dass man es am Ende einfach erlöschen kann. Eigene Route, einziger Unterschied, dass der Shopheader with hover injectet wird, mit irgendwelchen Template Daten, weil du musst es irgendwie füllen, das kommt dynamisch aktuell nicht aus dem API, damit man es erstens nicht vergisst, zweitens sich angucken kann und darüber nachdenken kann wie man damit umgehen will und drittens weil ich halt auch ganz gerne darauf umziehen wollen würde, aber mir aktuell noch unsicher bin, wie genau das dynamisch von starten gehen soll, aber das ist nicht die Aufgabe für heute. Testroute, Testdaten, Shopheader hover und wie gesagt hard codiert und nicht dynamisch die Daten, die dann in dem hover header angezeigt werden, nur die Kategorien bleiben natürlich noch dynamisch. Es kann auch gerne unter jeder Kategorie das gleiche stehen, darum geht es nicht, weil der Shopheader ja glaube ich schon props entgegen nimmt. Fragen, wenn nicht kannst du loslegen, wenn du Fragen hast, fragst du.

- Backend / Frontend - Paging diskrepanz für das Admin Dashboard analysieren
