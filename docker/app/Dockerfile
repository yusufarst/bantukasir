# The one image of BantuKasir (TASK-012; ADR-003 item 3, ADR-011 items 5 and 6;
# ENVIRONMENTS ENV-05): Nginx and PHP-FPM with the application, in the process
# role that its configuration selects (APP_PROCESS_ROLE).
#
#   runtime      what local, CI and the release share: PHP, Nginx, their
#                configuration, the PostgreSQL client tools and age
#   release      runtime + the application, without a development dependency or
#                a test fixture; runs as www-data; the target of the release
#   development  runtime + what the local environment and CI need (compose.yaml):
#                Composer, Git and Gitleaks, with the repository bind-mounted
#
# Every image is pinned by digest, Nginx by version and the PostgreSQL client
# tools by the server's major version; the other Debian packages come from the
# archive of the pinned base image at the time of the build, security updates
# included, and the image checks print them (scripts/checks/image-checks.sh).
# Versions and sources are recorded in docs/00-governance/TOOLCHAIN.md.

FROM composer:2.10.3@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS composer
FROM ghcr.io/gitleaks/gitleaks:v8.30.1@sha256:c00b6bd0aeb3071cbcb79009cb16a60dd9e0a7c60e2be9ab65d25e6bc8abbb7f AS gitleaks

# The frontend build. Node.js is used only here (ADR-002 item 1). The pages of
# the design system's specimen are for the browser tests only, and the build
# finds its pages by a glob, so they stay out of the release's chunks.
FROM node:24.22.0-trixie-slim@sha256:7f486cabe892bff8fef04c0f7da8dba84a560be3e4e2740b7a86afc57ebec315 AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.ts tsconfig.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY public ./public
RUN set -eux; \
    rm -rf resources/js/pages/Testing; \
    npx vite build

# The production Composer dependencies. Composer's own image has another set of
# PHP extensions, so the platform is not checked here: the application's image
# is, in the check of the runtime stage and by the image checks.
FROM composer AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY app ./app
RUN composer dump-autoload --no-dev --no-interaction --no-scripts --optimize --ignore-platform-reqs

FROM php:8.5.11-fpm-trixie@sha256:adde18b67ea7a8cddc580f657e0d3f16e800758c6fa690b969c2925a303e8afc AS runtime

# Nginx comes from the nginx.org stable repository, whose package history keeps
# every published version, so the pin below stays installable. The PostgreSQL
# client tools of the server's major version (compose.yaml) and age are for the
# role backup (ADR-010), from the PostgreSQL Apt Repository and Debian. Both
# repositories are verified by the fingerprint of their published signing key.
ARG NGINX_VERSION=1.30.6-1~trixie
ARG NGINX_KEY_FINGERPRINT=573BFD6B3D8FBC641079A6ABABF5BD827BD9BF62
ARG PGDG_SIGNER_FINGERPRINT=B97B0AFCAA1A47F044F244A07FCC7D46ACCC4CF8
ARG POSTGRES_MAJOR=18

# The second half keeps only what runs: the compilers and headers of the base
# image and of the extensions go, as the official PHP image removes its own.
RUN set -eux; \
    export DEBIAN_FRONTEND=noninteractive; \
    apt-get update; \
    apt-get install -y --no-install-recommends ca-certificates curl gnupg; \
    export GNUPGHOME="$(mktemp -d)"; \
    curl -fsSL https://nginx.org/keys/nginx_signing.key | gpg --dearmor -o /usr/share/keyrings/nginx-archive-keyring.gpg; \
    gpg --dry-run --quiet --no-keyring --import --import-options import-show /usr/share/keyrings/nginx-archive-keyring.gpg \
        | grep -q "${NGINX_KEY_FINGERPRINT}"; \
    curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /usr/share/keyrings/pgdg-archive-keyring.gpg; \
    gpg --dry-run --quiet --no-keyring --import --import-options import-show /usr/share/keyrings/pgdg-archive-keyring.gpg \
        | grep -q "${PGDG_SIGNER_FINGERPRINT}"; \
    rm -rf "$GNUPGHOME"; \
    echo "deb [signed-by=/usr/share/keyrings/nginx-archive-keyring.gpg] https://nginx.org/packages/debian trixie nginx" \
        > /etc/apt/sources.list.d/nginx.list; \
    echo "deb [signed-by=/usr/share/keyrings/pgdg-archive-keyring.gpg] https://apt.postgresql.org/pub/repos/apt trixie-pgdg main" \
        > /etc/apt/sources.list.d/pgdg.list; \
    printf 'Package: *\nPin: origin nginx.org\nPin-Priority: 900\n' > /etc/apt/preferences.d/99nginx; \
    apt-get update; \
    apt-get upgrade -y; \
    apt-get install -y --no-install-recommends "nginx=${NGINX_VERSION}" "postgresql-client-${POSTGRES_MAJOR}" age libpq-dev libpng-dev libjpeg62-turbo-dev libwebp-dev; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql; \
    # GD, the image library of the media pipeline (TASK-010; SECURITY_MODEL
    # SEC-UP-03), with PNG, JPEG and WebP only: libgd starts no other program.
    docker-php-ext-configure gd --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" gd; \
    apt-mark auto '.*' > /dev/null; \
    apt-mark manual ca-certificates curl nginx "postgresql-client-${POSTGRES_MAJOR}" age > /dev/null; \
    find /usr/local -type f -executable -exec ldd '{}' ';' 2>/dev/null \
        | awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); printf "*%s\n", so }' \
        | sort -u \
        | xargs -r dpkg-query --search \
        | grep -v '^diversion ' \
        | cut -d: -f1 \
        | sort -u \
        | xargs -r apt-mark manual > /dev/null; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    rm -rf /var/lib/apt/lists/* /usr/src/php.tar.xz /usr/src/php.tar.xz.asc; \
    cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"; \
    php -r 'exit(extension_loaded("gd") && extension_loaded("pdo_pgsql") ? 0 : 1);'; \
    nginx -v; \
    "/usr/lib/postgresql/${POSTGRES_MAJOR}/bin/pg_dump" --version; \
    age --version

COPY docker/app/nginx.conf /etc/nginx/nginx.conf
COPY docker/app/nginx-front-controller.conf /etc/nginx/bantukasir-front-controller.conf
COPY docker/app/nginx-security-headers.conf /etc/nginx/bantukasir-security-headers.conf
COPY docker/app/php.ini /usr/local/etc/php/conf.d/zz-bantukasir.ini
COPY docker/app/php-fpm.conf /usr/local/etc/php-fpm.d/zzz-bantukasir.conf
COPY --chmod=0755 docker/app/serve.sh /usr/local/bin/bantukasir-serve
COPY --chmod=0755 docker/app/healthcheck.sh /usr/local/bin/bantukasir-healthcheck

WORKDIR /var/www/html

# Role web: RH-01 on the listener of the health check, which needs a trusted
# host name and no trusted hop (TASK-007 finding F-1). Role backup serves
# nothing, so it is healthy while it runs.
HEALTHCHECK --interval=30s --timeout=12s --start-period=60s --retries=3 CMD ["/usr/local/bin/bantukasir-healthcheck"]

FROM runtime AS development
RUN set -eux; \
    export DEBIAN_FRONTEND=noninteractive; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip; \
    rm -rf /var/lib/apt/lists/*; \
    cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"; \
    git config --system --add safe.directory /var/www/html

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=gitleaks /usr/bin/gitleaks /usr/local/bin/gitleaks
COPY --chmod=0755 docker/app/entrypoint-local.sh /usr/local/bin/bantukasir-entrypoint

ENV COMPOSER_HOME=/tmp/composer

ENTRYPOINT ["/usr/local/bin/bantukasir-entrypoint"]

# The last stage, which a build without a target gives. The application's files
# belong to root, so that its processes can write only its runtime folders. The
# media folder exists here with its owner, so that no process of another user
# can create it first (TASK-010 finding F-8), and an empty volume mounted on it
# takes this owner and mode. No port is exposed: the platform's resource of role
# web names 8080, and role backup publishes none (ADR-010 item 6).
FROM runtime AS release
COPY artisan composer.json ./
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY lang ./lang
COPY public ./public
COPY resources/views ./resources/views
COPY routes ./routes
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY --chmod=0755 docker/app/entrypoint.sh /usr/local/bin/bantukasir-entrypoint

ENV LOG_CHANNEL=stderr LOG_LEVEL=info

RUN set -eux; \
    mkdir -p bootstrap/cache storage/app/private/media storage/framework/cache/data storage/framework/cache/locks storage/framework/sessions storage/framework/views storage/logs; \
    php artisan package:discover --no-interaction; \
    chown -R www-data:www-data bootstrap/cache storage; \
    chmod -R u=rwX,g=rX,o= bootstrap/cache storage; \
    chmod 0750 storage/app/private/media

USER www-data

ENTRYPOINT ["/usr/local/bin/bantukasir-entrypoint"]
