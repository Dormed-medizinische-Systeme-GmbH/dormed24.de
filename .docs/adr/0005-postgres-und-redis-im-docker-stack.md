# Postgres und Redis im Docker-Stack

Status: accepted (Oktober 2026)

Der Shop wechselt von SQLite auf Postgres und nutzt Redis für Queue, Cache und Sessions. Anlass sind die kommenden Hintergrund-Jobs (Newsletter-Versand, Social-Publishing), die einen Queue-Worker brauchen, und dass SQLite bei gleichzeitigen Schreibzugriffen (Sessions und Cache bei jedem Request) an Grenzen kommt.

Betrieben wird beides in einem Docker-Compose-Stack (`compose.yaml`), ein Prozess pro Container: App (Nginx + PHP-FPM), Queue-Worker und Scheduler aus demselben Image, ein Node-Container für SSR, Postgres 17 und Redis 7. Migrationen sind ein eigener Release-Schritt vor dem Start neuer Container, nicht Teil des Containerstarts. Lokal startet `compose.dev.yaml` nur Postgres und Redis; die App läuft weiter auf dem Rechner. Die CI testet gegen Postgres, damit Unterschiede zu SQLite (z. B. `LIKE` mit Groß-/Kleinschreibung, Reihenfolge bei gleichen Zeitstempeln) vor dem Deploy auffallen.

Vorhandene Daten zieht der einmalige Befehl `php artisan db:import-sqlite` um. Er ist bewusst eine Ausnahme von „keine eigenen Artisan-Commands“, weil er im App-Container ohne weitere Werkzeuge laufen soll.
