#!/bin/sh
# Startet den Container: prüft die Konfiguration, spielt neue Migrationen ein
# und übergibt dann an Supervisor (Nginx, PHP-FPM, Queue-Worker).
set -e

cd /app

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY ist nicht gesetzt — Start abgebrochen." >&2
    exit 1
fi

# Nur `migrate`, niemals `migrate:fresh` (das ist in Produktion ohnehin gesperrt).
touch database/database.sqlite
chown -R www-data:www-data storage bootstrap/cache database

php artisan migrate --force
php artisan storage:link --force || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
