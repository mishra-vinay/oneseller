#!/usr/bin/env bash
# Runs on the server, from the project root, after the new code has been pulled.
# Called by the deploy job in .github/workflows/ci.yml; safe to run by hand too.
set -euo pipefail

PHP="${PHP_BIN:-php}"
# Unquoted on purpose so it can be e.g. "php composer.phar".
COMPOSER="${COMPOSER_BIN:-composer}"

"$PHP" artisan down --retry=15 || true
trap '"$PHP" artisan up' EXIT

$COMPOSER install --no-dev --optimize-autoloader --no-interaction --no-progress
"$PHP" artisan migrate --force

if [ ! -e public/storage ]; then
    "$PHP" artisan storage:link
fi

# Caches config, routes, views and events.
"$PHP" artisan optimize

echo "Deployed $(git rev-parse --short HEAD)"
