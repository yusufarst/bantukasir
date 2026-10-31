#!/bin/sh
# Start-up of the local and CI application container (TASK-001; TASK-012), the
# development stage of the image's definition. On the first start it installs the
# Composer dependencies into vendor/ and creates a local .env with a generated
# application key; both are ignored by Git. It never migrates, seeds or deletes
# anything. Unlike the release's start it checks no setting and caches nothing,
# so that a change to the bind-mounted repository takes effect at once. The
# release image does not contain this file.
set -eu

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --no-progress
fi

if [ ! -f .env ]; then
    cp .env.example .env
fi

# key:generate replaces an existing APP_KEY line only, so a local .env without
# one gets an empty line first.
if ! grep -Eq '^APP_KEY=[^[:space:]]' .env; then
    grep -q '^APP_KEY=' .env || printf '\nAPP_KEY=\n' >> .env
    php artisan key:generate --no-interaction --force
fi

# PHP-FPM runs as www-data and must write the framework's runtime folders, also
# when the bind-mounted files belong to another user (Linux runners). A Windows
# bind mount may not support chmod; its files are already writable there.
chmod -R a+rwX storage bootstrap/cache 2>/dev/null || true

exec /usr/local/bin/bantukasir-serve
