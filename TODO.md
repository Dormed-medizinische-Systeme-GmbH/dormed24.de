# TODO — dormed-shop

## IDEEN

- DPD Tracking in App per API einbauen
- Bewertungen order-basiert reaktivieren (Kunde bewertet nur gekaufte Produkte) — Route + Formular sind bis dahin bewusst deaktiviert (siehe `ANALYSE.md`); `RatingController` + Tests liegen als Grundlage bereit

---

## Deployment (test.dormed24.de) — offene Punkte

- **Zertifikat/Domain-Übergang zurückstellen:** `APP_URL` läuft aktuell übergangsweise auf `https://test.dormed.de` (gültiges TLS-Zertifikat), eigentliche Zieldomain ist `test.dormed24.de`. Sobald dafür ein echtes Zertifikat existiert: `APP_URL` in der Server-`.env` zurückstellen, danach `php artisan optimize:clear && php artisan optimize` (sonst bleibt der alte Wert gecacht — ist uns schon einmal passiert).
- **Admin-Zugang in der Server-Env setzen:** `ADMIN_EMAIL`, `ADMIN_PASSWORD` (bcrypt-Hash, z. B. `php artisan tinker --execute 'echo Hash::make("…");'`), danach `php artisan optimize`. Die alte `users`-Tabelle wird nicht mehr genutzt und kann nach erfolgreichem Umstieg per Migration entfernt werden.
- **DB-Backup-Strategie klären** (SQLite-Datei, keine Automatisierung vorhanden).
- `lint.yml` ist aktuell ein weiches Gate (nutzt `--fix`/`--write`, scheitert nicht an Formatierungs-Drift). Scharf machen: einmal `npm run format` + `vendor/bin/pint` committen, dann in `lint.yml` auf die `:check`-Varianten umstellen (`composer ci:check` bündelt das Gate schon).
- SSH-Deploy-Key ist aktuell ein Account-Key (Zugriff auf alle Repos von `DormedGmbH`). Auf einen repo-gebundenen, read-only **Deploy Key** umstellen (least privilege).

---

## Sicherheit (Backlog aus Code-Audit 2026-06-25)

### 🟢 FormRequest authorize() gibt überall true zurück

**Dateien:** `UpdateProductRequest`, `UpdateCategoryRequest`, `StoreRatingRequest`, etc.
Nur Route-Ebene schützt. Für feinere Permissions später Laravel Policies einführen.

---

## UI/UX — Kundenbereich

- [ ] **Produktfilter** — Hersteller-Filter vorhanden (Sheet auf den Produktlisten). Fachrichtungen nur als UI ohne Daten; Preis/Rating fehlen.
- [ ] **Alte Daten aufräumen** — nach dem Umzug in die Env: PayPal-/SMTP-Zeilen aus `settings` und die `users`-Tabelle per Migration entfernen (vorher sicherstellen, dass alle Zugangsdaten in der Env stehen).

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

- Backend / Frontend - Paging diskrepanz für das Admin Dashboard analysieren
