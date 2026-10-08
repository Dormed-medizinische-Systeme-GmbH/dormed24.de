#!/bin/sh
# Startet den Container: prüft die Konfiguration, spielt neue Migrationen ein
# und übergibt dann an Supervisor (Nginx, PHP-FPM, Queue-Worker).
set -e

cd /app

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY ist nicht gesetzt — Start abgebrochen." >&2
    exit 1
fi

chown -R www-data:www-data storage bootstrap/cache

# Postgres kann beim gemeinsamen Start des Stacks noch hochfahren: bis zu
# 60 Sekunden auf die Datenbank warten, dann erst migrieren.
attempts=0
until php artisan db:show >/dev/null 2>&1; do
    attempts=$((attempts + 1))
    if [ "$attempts" -ge 30 ]; then
        echo "Datenbank nicht erreichbar — Start abgebrochen." >&2
        exit 1
    fi
    sleep 2
done

# Nur `migrate`, niemals `migrate:fresh` (das ist in Produktion ohnehin gesperrt).
php artisan migrate --force
php artisan storage:link --force || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
