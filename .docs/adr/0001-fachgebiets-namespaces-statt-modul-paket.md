# Fachgebiets-Namespaces statt Modul-Paket

Status: accepted (Oktober 2026)

Der Shop wächst um neue Gebiete (Marketing: Newsletter, Social-Media, Magazin). Wir ordnen die **Geschäftslogik** nach Fachgebieten (Core, Shop, Orders, Marketing), führen aber **kein Modul-Paket** wie `nwidart/laravel-modules` ein. Bei rund 7.000 Zeilen PHP und einem Team brächte ein solches Paket mehr Konfiguration und mehr Interface, aber keine tieferen Module.

Controller, Requests, Models, Mails und Migrationen bleiben an den Orten, die Laravel und Wayfinder erwarten. Umgezogen wird erst, wenn ein Fachgebiet ein tiefes Modul mit kleinem Interface hat; reines Verschieben von Dateien ist kein Ziel. Bis zur Freigabe eines eigenen Basisordners liegt die Logik unter `app/Support/<Fachgebiet>`.

Plan: `.todo/architektur-plan.md`.
