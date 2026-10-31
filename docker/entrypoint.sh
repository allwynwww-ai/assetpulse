#!/bin/sh
# AssetPulse container entrypoint: prepare Laravel, then run php-fpm + nginx in the foreground.
set -eu
cd /var/www/html

log() { echo "[entrypoint] $*"; }

export PORT="${PORT:-10000}"
# Render exposes the public URL; use it unless APP_URL was set explicitly.
if [ -z "${APP_URL:-}" ] && [ -n "${RENDER_EXTERNAL_URL:-}" ]; then export APP_URL="$RENDER_EXTERNAL_URL"; fi

# Laravel expects APP_KEY as "base64:<32 bytes>". Render's generateValue yields a bare
# base64 256-bit string, so add the prefix when it is missing.
if [ -n "${APP_KEY:-}" ]; then
  case "$APP_KEY" in base64:*) ;; *) export APP_KEY="base64:${APP_KEY}" ;; esac
fi

# Any other command (e.g. `php artisan tinker`, a Render shell/job) runs as-is
if [ "${1:-serve}" != "serve" ]; then exec "$@"; fi

if [ -z "${APP_KEY:-}" ]; then log "ERROR: APP_KEY is not set"; exit 1; fi

mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Never use a stale cache from the image build
php artisan optimize:clear >/dev/null 2>&1 || true

# Wait for the database (a free Render Postgres may still be starting) and migrate
tries=0
until php artisan migrate --force --no-interaction; do
  tries=$((tries + 1))
  if [ "$tries" -ge "${DB_WAIT_TRIES:-20}" ]; then log "database not reachable / migration failed"; exit 1; fi
  log "migrate failed (attempt $tries) - retrying in 3s"; sleep 3
done

# Demo data (DEMO_SEED=true) or a Super Admin (ADMIN_EMAIL/ADMIN_PASSWORD) - only when no users exist
php artisan app:seed-if-empty --no-interaction

php artisan storage:link --force >/dev/null 2>&1 || true
chown -R www-data:www-data storage
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache || true

envsubst '${PORT}' < /etc/nginx/templates/assetpulse.conf.template > /etc/nginx/conf.d/assetpulse.conf
nginx -t -q

log "starting php-fpm + nginx on port ${PORT}"
php-fpm &
FPM_PID=$!
nginx -g 'daemon off;' &
NGINX_PID=$!

STOPPING=0
term() {
  [ "$STOPPING" = 1 ] && return 0
  STOPPING=1; log "shutting down"
  kill -QUIT "$NGINX_PID" 2>/dev/null || true
  kill -QUIT "$FPM_PID" 2>/dev/null || true
}
trap term TERM INT QUIT

# Exit when either process dies: 0 after a requested stop, 1 on a crash (so the platform restarts us)
while kill -0 "$FPM_PID" 2>/dev/null && kill -0 "$NGINX_PID" 2>/dev/null; do sleep 2; done
[ "$STOPPING" = 1 ] || log "a server process exited unexpectedly"
CODE=$STOPPING
term
wait || true
[ "$CODE" = 1 ] && exit 0
exit 1
