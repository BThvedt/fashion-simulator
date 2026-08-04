#!/bin/sh
#
# Prod container entrypoint. The application (including web/sites/default/files
# and the private:// fallback) is bind-mounted from the host, where it's owned
# by the deploy user. PHP-FPM runs its workers as www-data, so without this
# fixup www-data can't create the runtime directories Drupal writes into
# (image-style derivatives, CSS/JS aggregation, the Twig PHP store, the private
# files fallback), producing "mkdir(): Permission denied" warnings and broken
# asset generation.
#
# We run as root here (before dropping to www-data via php-fpm) and make just
# the runtime-writable trees owned by/writable to www-data. Everything else
# (code, vendor, config/sync) is left untouched so `git pull` deploys keep
# working. Config sync lives in ../config/sync in prod, so the writable set is
# only the files dir and the private fallback.
set -e

APP_ROOT=/var/www/html
WRITABLE_DIRS="$APP_ROOT/web/sites/default/files $APP_ROOT/private"

if [ "$(id -u)" = "0" ]; then
  for dir in $WRITABLE_DIRS; do
    mkdir -p "$dir"
    # Best-effort: don't abort the container if the host FS rejects a chown.
    chown -R www-data:www-data "$dir" 2>/dev/null || true
    chmod -R u+rwX "$dir" 2>/dev/null || true
  done
fi

# Hand off to the stock php image entrypoint (sets up PHP, then execs CMD).
exec docker-php-entrypoint "$@"
