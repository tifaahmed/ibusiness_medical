#!/bin/bash
# ============================================================
# Deploy script — run this after every file upload / git pull
# Usage: bash deploy.sh
#        bash deploy.sh --skip-build   (if assets already built locally)
# ============================================================
set -e

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$APP_DIR"

SKIP_BUILD=false
for arg in "$@"; do
    [[ "$arg" == "--skip-build" ]] && SKIP_BUILD=true
done

echo ""
echo "=== ASH Healthcare Deploy ==="
echo ""

# 1. Clear all Laravel caches first so nothing stale is carried over
echo "[1/8] Clearing Laravel caches..."
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear
echo "      Done."

# 2. Reload PHP-FPM to wipe OPcache so new PHP files (web.php etc.) are picked up
echo "[2/8] Reloading PHP-FPM (clears OPcache)..."
systemctl reload php8.4-fpm
echo "      Done."

# 3. Regenerate Ziggy routes file for the JS build
echo "[3/8] Regenerating Ziggy routes..."
php artisan ziggy:generate resources/js/ziggy.js --url=https://ashhealthcare-eg.com
echo "      Done."

# 4. Build frontend assets (skip with --skip-build if built locally)
if [ "$SKIP_BUILD" = false ]; then
    echo "[4/8] Building frontend assets (npm run build)..."
    npm run build
    echo "      Done."
else
    echo "[4/8] Skipping npm build (--skip-build passed)."
fi

# 5. Cache config and views for production performance
echo "[5/8] Caching config and views for production..."
php artisan config:cache
php artisan view:cache
echo "      Done."

# 6. Run any pending database migrations
echo "[6/8] Running migrations..."
php artisan migrate --force
echo "      Done."

# 7. Sync permissions/roles — IMPORTANT: run this every deploy so any new
#    permissions added to UserPermissionEnum are automatically granted to
#    super_admin and other roles. Without this, new route permissions won't
#    work even if they are defined in the enum.
echo "[7/8] Syncing roles and permissions..."
php artisan db:seed --class=AdminRoleSeeder --force
php artisan db:seed --class=PermissionSeeder --force
echo "      Done."

# 8. Restart queue workers if any are running
echo "[8/8] Restarting queue workers..."
php artisan queue:restart
echo "      Done."

echo ""
echo "=== Deploy complete! ==="
echo ""
