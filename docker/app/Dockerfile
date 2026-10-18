# Application container of the local and CI environment (TASK-001): Nginx and
# PHP-FPM serving Laravel from the bind-mounted repository. TASK-012 replaces it
# with the release image. Every image is pinned by digest and Nginx by version;
# the other Debian packages come from the archive of the pinned base image.
# Versions and sources are recorded in docs/00-governance/TOOLCHAIN.md.

FROM composer:2.10.3@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS composer
FROM ghcr.io/gitleaks/gitleaks:v8.30.1@sha256:c00b6bd0aeb3071cbcb79009cb16a60dd9e0a7c60e2be9ab65d25e6bc8abbb7f AS gitleaks

FROM php:8.5.11-fpm-trixie@sha256:adde18b67ea7a8cddc580f657e0d3f16e800758c6fa690b969c2925a303e8afc

# Nginx comes from the nginx.org stable repository, whose package history keeps
# every published version, so the pin below stays installable.
ARG NGINX_VERSION=1.30.6-1~trixie
ARG NGINX_KEY_FINGERPRINT=573BFD6B3D8FBC641079A6ABABF5BD827BD9BF62

RUN set -eux; \
    export DEBIAN_FRONTEND=noninteractive; \
    apt-get update; \
    apt-get install -y --no-install-recommends ca-certificates curl gnupg git unzip libpq-dev; \
    export GNUPGHOME="$(mktemp -d)"; \
    curl -fsSL https://nginx.org/keys/nginx_signing.key | gpg --dearmor -o /usr/share/keyrings/nginx-archive-keyring.gpg; \
    gpg --dry-run --quiet --no-keyring --import --import-options import-show /usr/share/keyrings/nginx-archive-keyring.gpg \
        | grep -q "${NGINX_KEY_FINGERPRINT}"; \
    rm -rf "$GNUPGHOME"; \
    echo "deb [signed-by=/usr/share/keyrings/nginx-archive-keyring.gpg] https://nginx.org/packages/debian trixie nginx" \
        > /etc/apt/sources.list.d/nginx.list; \
    printf 'Package: *\nPin: origin nginx.org\nPin-Priority: 900\n' > /etc/apt/preferences.d/99nginx; \
    apt-get update; \
    apt-get install -y --no-install-recommends "nginx=${NGINX_VERSION}"; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql; \
    apt-get purge -y --auto-remove gnupg; \
    rm -rf /var/lib/apt/lists/*; \
    cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"; \
    git config --system --add safe.directory /var/www/html

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=gitleaks /usr/bin/gitleaks /usr/local/bin/gitleaks
COPY docker/app/nginx.conf /etc/nginx/nginx.conf
COPY --chmod=0755 docker/app/entrypoint.sh /usr/local/bin/bantukasir-entrypoint

ENV COMPOSER_HOME=/tmp/composer
WORKDIR /var/www/html
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/bantukasir-entrypoint"]
