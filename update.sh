#!/bin/bash
set -e

# Sandbox-Reset — NICHT der Produktions-Deploy (der lebt in
# .github/workflows/deploy.yml und laeuft automatisch bei jedem Push auf
# main). Code, Dependencies und Assets sind auf dem Server bereits aktuell,
# der Deploy haelt sie bei jedem Push frisch — dieses Skript pullt/baut
# deshalb bewusst NICHT erneut, um keine zweite, potenziell abweichende
# Build-Quelle neben der CI zu haben.
#
# Einzige Aufgabe hier: der destruktive Sandbox-Reset (migrate:fresh --seed),
# den der automatische Deploy bewusst NIE macht.

echo "=== dormed-shop Sandbox-Reset ==="
echo ""

echo "🧹 Caches leeren..."
php artisan optimize:clear

echo ""
echo "🗄️  Datenbank zuruecksetzen und neu seeden..."
php artisan migrate:fresh --seed --force

echo ""
echo "⚡ Caches neu aufbauen..."
php artisan optimize

echo ""
echo "🔄 SSR-Server neu laden (systemd startet ihn per Restart=always neu)..."
php artisan inertia:stop-ssr 2>/dev/null || true

echo ""
echo "✅ Sandbox-Reset abgeschlossen!"
echo "   $(date)"
