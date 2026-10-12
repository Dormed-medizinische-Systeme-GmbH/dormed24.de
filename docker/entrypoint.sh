#!/bin/sh
# Einstieg für alle Container aus dem App-Image (Web, Queue, Scheduler,
# einmalige Befehle wie `php artisan migrate`). Prüft die Konfiguration,
# baut die Caches und startet dann den eigentlichen Befehl.
#
# Migrationen laufen hier bewusst nicht: Sie sind ein eigener Release-Schritt
# vor dem Start der neuen Container (siehe README, Abschnitt Docker-Stack).
set -e

cd /app

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY ist nicht gesetzt — Start abgebrochen." >&2
    exit 1
fi

# Läuft der Container als root, gehört storage www-data, und alles außer
# Supervisor (der Nginx und PHP-FPM als root startet) läuft als www-data.
# So legt kein Worker oder Artisan-Befehl Dateien an, die der Webserver
# danach nicht mehr schreiben darf.
as_app_user() {
    if [ "$(id -u)" = "0" ]; then
        setpriv --reuid=www-data --regid=www-data --init-groups "$@"
    else
        "$@"
    fi
}

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage
fi

case "$1" in
    */supervisord)
        # Web: alle Caches inklusive Views (liegen im geteilten storage-Volume).
        as_app_user php artisan optimize
        exec "$@"
        ;;
    *)
        # Worker, Scheduler, einmalige Befehle: nur die Konfiguration cachen,
        # damit nicht mehrere Container gleichzeitig Views kompilieren.
        as_app_user php artisan config:cache >/dev/null
        if [ "$(id -u)" = "0" ]; then
            exec setpriv --reuid=www-data --regid=www-data --init-groups "$@"
        fi
        exec "$@"
        ;;
esac
