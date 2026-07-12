#!/bin/bash
set -e

echo "=== dormed-shop Update ==="
echo ""

# 1. Git pull
echo "📦 Pulling latest code..."
git pull origin main

# 2. Composer install
echo ""
echo "📦 Installing/updating Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader

# 3. Clear Laravel caches before route generation/build
echo ""
echo "🧹 Clearing Laravel caches before build..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# 4. NPM install & build
echo ""
echo "📦 Installing NPM dependencies..."
npm install --no-audit --no-fund

echo ""
echo "⚙️  Generating Wayfinder routes..."
php artisan wayfinder:generate --with-form

echo ""
echo "🔨 Building frontend assets (Client + SSR-Bundle)..."
# build:ssr baut Client-Assets UND das SSR-Bundle (bootstrap/ssr/app.js).
# Auch wenn lokal CSR läuft (INERTIA_SSR_ENABLED=false): so bleibt das
# SSR-Bundle konsistent, falls das Flag mal auf true steht. Braucht Node ≥ 20.19.
npm run build:ssr

# 5. Run migrations
echo ""
echo "🗄️  Running database migrations..."
php artisan migrate:fresh --seed --force

# 6. Clear caches
echo ""
echo "🧹 Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# 7. Restart queue (if running)
echo ""
echo "🔄 Restarting queue workers..."
php artisan queue:restart 2>/dev/null || true

# 7b. Reload SSR server (only effective when INERTIA_SSR_ENABLED=true UND ein
#     Prozessmanager wie Supervisor/systemd den Prozess mit dem neuen Bundle
#     wieder hochbringt). Bei lokalem CSR ist das ein harmloser No-op.
echo ""
echo "🔄 Reloading SSR server (if enabled)..."
php artisan inertia:stop-ssr 2>/dev/null || true

# 8. Re-cache for production
echo ""
echo "⚡ Re-caching for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 9. Set permissions
echo ""
echo "🔐 Setting storage permissions..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo ""
echo "✅ Update complete!"
echo "   $(date)"
