#!/usr/bin/env bash
# The image checks (RELEASE_PROCESS section 4 items 1 to 4; TASK-012 scope items 2
# to 4 and 6; AC-012-01 to AC-012-05): on the release stage of docker/app/Dockerfile,
# on CI's runner, with synthetic data and an ephemeral PostgreSQL. Nothing here is
# run on the Owner's machine (D-108), and nothing is published.
#
#   1. the definition: every base image pinned by digest; a rebuild without a cache
#      gives the same packages and application files;
#   2. no development dependency or test fixture in the image;
#   3. role web against an empty database: it starts and migrates nothing; with the
#      migrations applied /up answers 200; the application is served;
#   4. private paths, dotfiles, vendor/ and storage/ unreachable through Nginx;
#   5. the body limits of RT-12 at Nginx and PHP, and the 60-second termination;
#   6. role backup serves nothing, publishes no port and registers only SC-03;
#   7. each role refuses a missing or default setting, and prints no value;
#   8. a secret scan of the image's file system and a vulnerability scan of its
#      packages; the package lists for the evidence (AC-012-01).
#
# Every check runs even after a failure, so that one run reports them all. The
# secrets are generated here and live for this run only (EXECUTION_CONTEXT EXC-63).
set -uo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$root" || exit 2

# Pinned here or read from where they are already pinned.
grype_image='anchore/grype:v0.121.0@sha256:621d56d1d68106b787022c81546f7e0a36202a25d1239f0c20195fab8c16f7ea'
postgres_image="$(sed -n 's/^ *image: \(postgres:[^ ]*\)$/\1/p' compose.yaml | head -n 1)"
gitleaks_image="$(sed -n 's/^FROM \(ghcr\.io\/gitleaks\/[^ ]*\) AS gitleaks$/\1/p' docker/app/Dockerfile)"

image=bantukasir-release:check
rebuild=bantukasir-release:rebuild
network=bk-image-network
database=bk-image-database
web=bk-image-web
untrusted=bk-image-untrusted
stub=bk-image-stub
backup=bk-image-backup
refusal=bk-image-refusal
rootfs_container=bk-image-rootfs

host=image-check.test
other_host=second.image-check.test
database_name=image_check

work="$(mktemp -d "${TMPDIR:-/tmp}/bantukasir-image-checks.XXXXXX")"
passes=0
failures=0

cleanup() {
    docker rm -f "$web" "$untrusted" "$stub" "$backup" "$database" "$refusal" "$rootfs_container" >/dev/null 2>&1
    docker network rm "$network" >/dev/null 2>&1
    rm -rf "$work"
}
trap cleanup EXIT

pass() {
    passes=$((passes + 1))
    echo "PASS $1"
}

fail() {
    failures=$((failures + 1))
    echo "FAIL $1"
    echo "::error::$1"
}

# expect <description> <expected> <actual>
expect() {
    if [ "$2" = "$3" ]; then pass "$1"; else fail "$1: expected [$2], got [$3]"; fi
}

# expect_ok <description> <command...>
expect_ok() {
    local description="$1"
    shift
    if "$@"; then pass "$description"; else fail "$description"; fi
}

# expect_refused <description> <command...>
expect_refused() {
    local description="$1"
    shift
    if "$@"; then fail "$description"; else pass "$description"; fi
}

section() {
    echo "::endgroup::"
    echo "::group::$1"
}

# request <container> <path> [curl arguments...]: the status of a request to the
# public listener from inside the container, as the trusted proxy hop sends it;
# the body and headers stay in the container for body_of and header_of.
request() {
    local container="$1" path="$2"
    shift 2
    docker exec "$container" bash -c 'rm -f /tmp/check-body /tmp/check-headers; exec curl "$@"' _ \
        -sS --path-as-is --max-time 90 -o /tmp/check-body -D /tmp/check-headers -w '%{http_code}' \
        -H "Host: $host" -H 'X-Forwarded-Proto: https' "$@" "http://127.0.0.1:8080$path"
}

body_of() {
    docker exec "$1" bash -c 'cat /tmp/check-body 2>/dev/null || true'
}

header_of() {
    docker exec "$1" bash -c 'grep -i "^$0:" /tmp/check-headers || true' "$2" | tr -d '\r'
}

# The addresses a container listens on, without 127.0.0.11, which is Docker's own
# resolver in that network namespace.
listeners_of() {
    docker exec "$1" bash -c 'cat /proc/net/tcp /proc/net/tcp6 2>/dev/null' | awk '$4 == "0A" && $2 !~ /^0B00007F:/ { print $2 }' | sort | tr '\n' ' ' | sed 's/ $//'
}

random_text() {
    head -c 24 /dev/urandom | base64 -w 0 | tr -dc 'A-Za-z0-9'
}

echo "::group::The definition"

# ---------------------------------------------------------------- 1. The definition

unpinned="$(awk '
    toupper($1) == "FROM" {
        reference = $2
        name = (toupper($3) == "AS") ? $4 : ""
        if (!(reference in stages) && reference !~ /@sha256:[0-9a-f]+$/) print reference
        if (name != "") stages[name] = 1
    }' docker/app/Dockerfile)"
expect 'every base image of the definition is pinned by digest' '' "$unpinned"
expect_ok 'the PostgreSQL image is pinned by digest' grep -Eq '@sha256:[0-9a-f]{64}$' <<<"$postgres_image"
expect_ok 'the Gitleaks image is pinned by digest' grep -Eq '@sha256:[0-9a-f]{64}$' <<<"$gitleaks_image"
expect_refused 'the start of the release never migrates or seeds' grep -Eq 'artisan +(migrate|db:)' docker/app/entrypoint.sh docker/app/serve.sh
expect_refused 'the local start never migrates or seeds' grep -Eq 'artisan +(migrate|db:)' docker/app/entrypoint-local.sh

section 'The build'
if ! docker build --target release -f docker/app/Dockerfile -t "$image" .; then
    fail 'the release image builds'
    exit 1
fi
pass 'the release image builds'
echo "Image identifier: $(docker inspect --format '{{.Id}}' "$image")"
echo "Image size in bytes: $(docker inspect --format '{{.Size}}' "$image")"
grep -E '^FROM ' docker/app/Dockerfile | grep '@sha256:'
echo "$postgres_image"
echo "$grype_image"

section 'A rebuild without a cache'
# The packages and the application's files of a build, by name and SHA-256.
manifest_of() {
    docker run --rm "$1" bash -c 'dpkg-query -W -f="\${Package} \${Version} \${Architecture}\n"; cd /var/www/html && find . -type f -not -path "./storage/*" -print0 | sort -z | xargs -0 sha256sum'
}
if docker build --no-cache --target release -f docker/app/Dockerfile -t "$rebuild" . >"$work/rebuild.log" 2>&1; then
    manifest_of "$image" >"$work/manifest-first"
    manifest_of "$rebuild" >"$work/manifest-second"
    if diff "$work/manifest-first" "$work/manifest-second" >"$work/manifest-diff"; then
        pass "a rebuild gives the same packages and files ($(wc -l <"$work/manifest-first") entries)"
    else
        cat "$work/manifest-diff"
        fail 'a rebuild gives the same packages and files'
    fi
else
    tail -n 40 "$work/rebuild.log"
    fail 'the rebuild without a cache succeeds'
fi
docker rmi "$rebuild" >/dev/null 2>&1

# ---------------------------------------------------------- 2. What the image holds

section 'What the image holds'
contents="$(docker run --rm "$image" bash -c '
    cd /var/www/html
    for path in tests node_modules .env .env.example .git .github docs scripts phpunit.xml phpstan.neon package.json resources/js resources/css public/hot database/factories database/seeders compose.yaml docker; do
        [ ! -e "$path" ] || echo "present $path"
    done
    for program in composer gitleaks node npm gcc cc make git; do
        ! command -v "$program" >/dev/null 2>&1 || echo "program $program"
    done
    find public/build -iname "*specimen*" -exec echo "fixture {}" ";"
    grep -l "pages/Testing" public/build/manifest.json | sed "s/^/fixture /"
    for package in phpunit/phpunit phpstan/phpstan larastan/larastan laravel/pint; do
        [ ! -e "vendor/$package" ] || echo "dev-package $package"
    done
    php -r "\$i = json_decode(file_get_contents(\"vendor/composer/installed.json\"), true); if ((\$i[\"dev\"] ?? true) !== false || (\$i[\"dev-package-names\"] ?? [\"unknown\"]) !== []) { echo \"dev-package the installed list\n\"; }"
    [ -f public/build/manifest.json ] || echo "absent public/build/manifest.json"
    [ -f public/fonts/inter/OFL.txt ] || echo "absent public/fonts/inter/OFL.txt"
')"
expect 'no development dependency or fixture is in the image, and the build is' '' "$contents"
expect 'the image runs as www-data' 'www-data' "$(docker inspect --format '{{.Config.User}}' "$image")"
expect 'the image declares no port but the base image'\''s 9000, where nothing can be reached' '{"9000/tcp":{}}' "$(docker inspect --format '{{json .Config.ExposedPorts}}' "$image")"
expect_ok 'the image has a health check' test -n "$(docker inspect --format '{{json .Config.Healthcheck.Test}}' "$image")"
expect 'the media folder belongs to the runtime user, only for it and its group' 'www-data 750' \
    "$(docker run --rm "$image" stat -c '%U %a' storage/app/private/media)"
expect_refused 'the application files cannot be changed by the runtime user' docker run --rm "$image" bash -c 'touch app/probe || touch public/probe'
expect 'PHP has GD with PNG, JPEG and WebP, and pdo_pgsql' 'ok' \
    "$(docker run --rm "$image" php -r 'echo extension_loaded("pdo_pgsql") && function_exists("imagecreatefrompng") && function_exists("imagecreatefromjpeg") && function_exists("imagecreatefromwebp") ? "ok" : "absent";')"
expect 'the memory limit may be raised for one image (TASK-010 F-5)' '384M' \
    "$(docker run --rm "$image" php -r 'ini_set("memory_limit", "384M"); echo ini_get("memory_limit");')"
expect 'the client tools are of the pinned server major version' '18' \
    "$(docker run --rm "$image" pg_dump --version | sed -E 's/.* ([0-9]+)\.[0-9]+.*/\1/')"
expect_ok 'age is present' docker run --rm "$image" age --version

# ------------------------------------------------------------------- The environment

section 'The environment'
app_key="base64:$(head -c 32 /dev/urandom | base64 -w 0)"
superuser_password="$(random_text)"
migration_password="$(random_text)"
runtime_password="$(random_text)"
backup_password="$(random_text)"
store_key_id="$(random_text)"
store_key="$(random_text)"
web_ping="$(random_text)"
reconciliation_ping="$(random_text)"
backup_ping="$(random_text)"
recipient="$(docker run --rm "$image" age-keygen 2>/dev/null | sed -n 's/^# public key: //p')"
expect_ok 'age-keygen gives a recipient' test -n "$recipient"

secrets="$work/secrets"
printf '%s\n' "$app_key" "$superuser_password" "$migration_password" "$runtime_password" "$backup_password" \
    "$store_key_id" "$store_key" "$web_ping" "$reconciliation_ping" "$backup_ping" >"$secrets"

no_secret_in() {
    ! grep -F -q -f "$secrets" "$1"
}

web_env="$work/web.env"
cat >"$web_env" <<EOF
APP_ENV=production
APP_ENVIRONMENT_NAME=staging
APP_PROCESS_ROLE=web
APP_URL=https://$host
APP_TRUSTED_HOSTS=$host,$other_host
APP_TRUSTED_PROXIES=127.0.0.1/32
APP_KEY=$app_key
DB_HOST=$database
DB_DATABASE=$database_name
DB_USERNAME=image_runtime
DB_PASSWORD=$runtime_password
HEARTBEAT_WEB_URL=https://heartbeat.image-check.test/$web_ping
HEARTBEAT_RECONCILIATION_URL=https://heartbeat.image-check.test/$reconciliation_ping
EOF

backup_env="$work/backup.env"
cat >"$backup_env" <<EOF
APP_ENV=production
APP_ENVIRONMENT_NAME=staging
APP_PROCESS_ROLE=backup
DB_HOST=$database
DB_DATABASE=$database_name
BACKUP_DB_USERNAME=image_backup
BACKUP_DB_PASSWORD=$backup_password
BACKUP_STORE_ENDPOINT=https://store.image-check.test
BACKUP_STORE_BUCKET=image-check-bucket
BACKUP_STORE_KEY_ID=$store_key_id
BACKUP_STORE_KEY=$store_key
BACKUP_RECIPIENT=$recipient
HEARTBEAT_BACKUP_URL=https://heartbeat.image-check.test/$backup_ping
EOF

migration_env="$work/migration.env"
cat >"$migration_env" <<EOF
APP_ENV=production
APP_ENVIRONMENT_NAME=staging
DB_HOST=$database
DB_DATABASE=$database_name
DB_MIGRATION_USERNAME=image_migration
DB_MIGRATION_PASSWORD=$migration_password
EOF

docker network create "$network" >/dev/null
docker run -d --name "$database" --network "$network" --tmpfs /var/lib/postgresql \
    -e POSTGRES_PASSWORD="$superuser_password" "$postgres_image" >/dev/null
for _ in $(seq 1 60); do
    docker exec "$database" pg_isready -q -h 127.0.0.1 -U postgres && break
    sleep 1
done
expect_ok 'the ephemeral PostgreSQL answers' docker exec "$database" pg_isready -q -h 127.0.0.1 -U postgres
expect_ok 'the setup script of ENV-30 creates the database and its roles, with their passwords' \
    docker run --rm --network "$network" -v "$root/database/setup:/setup:ro" \
    -e PGHOST="$database" -e PGUSER=postgres -e PGPASSWORD="$superuser_password" \
    -e BANTUKASIR_SETUP_DATABASE="$database_name" -e BANTUKASIR_SETUP_MARKER=staging \
    -e BANTUKASIR_SETUP_MIGRATION_ROLE=image_migration -e BANTUKASIR_SETUP_RUNTIME_ROLE=image_runtime -e BANTUKASIR_SETUP_BACKUP_ROLE=image_backup \
    -e BANTUKASIR_SETUP_MIGRATION_PASSWORD="$migration_password" -e BANTUKASIR_SETUP_RUNTIME_PASSWORD="$runtime_password" -e BANTUKASIR_SETUP_BACKUP_PASSWORD="$backup_password" \
    "$postgres_image" psql -X -v ON_ERROR_STOP=1 -d postgres -f /setup/setup.sql

# wait_for_web <container>: until the public listener answers anything.
wait_for_web() {
    local answer
    for _ in $(seq 1 60); do
        answer="$(docker exec "$1" curl -s -o /dev/null --max-time 5 -w '%{http_code}' http://127.0.0.1:8080/fonts/inter/OFL.txt 2>/dev/null)"
        [ "$answer" = 200 ] && return 0
        [ "$(docker inspect --format '{{.State.Running}}' "$1" 2>/dev/null)" = true ] || break
        sleep 1
    done
    echo "The container $1 did not start; its log:"
    docker logs --tail 60 "$1" 2>&1
    return 1
}

# ------------------------------------------------------------------------ 3. Role web

section 'Role web, before and after the migrations'
docker run -d --name "$web" --network "$network" --env-file "$web_env" "$image" >/dev/null
expect_ok 'role web starts without a migration having run' wait_for_web "$web"
expect 'no container start ran a migration' '' \
    "$(docker exec "$database" psql -U postgres -d "$database_name" -Atc "SELECT to_regclass('public.migrations')")"
expect 'before the migrations /up answers an error and says nothing' '503' "$(request "$web" /up)"
expect 'before the migrations /up has an empty body' '' "$(body_of "$web")"
expect_refused 'role web cannot run the migrations: it holds no such credential' docker exec "$web" php artisan migrate --force

expect_ok 'the migration job applies the migrations, as the migration role, by an explicit command' \
    docker run --rm --network "$network" --env-file "$migration_env" "$image" php artisan migrate --database=pgsql_migration --force
expect_ok 'the migrations table now exists' test -n \
    "$(docker exec "$database" psql -U postgres -d "$database_name" -Atc "SELECT to_regclass('public.migrations')")"

expect '/up answers 200' '200' "$(request "$web" /up)"
expect '/up says nothing' '' "$(body_of "$web")"
expect_ok 'the health check of the image passes' docker exec "$web" /usr/local/bin/bantukasir-healthcheck
expect 'the configuration, routes and views are cached' 'ok' \
    "$(docker exec "$web" bash -c '[ -f bootstrap/cache/config.php ] && ls bootstrap/cache/routes-*.php >/dev/null && ls storage/framework/views/*.php >/dev/null && echo ok')"
expect 'the cached configuration is for its user only' 'www-data 640' "$(docker exec "$web" stat -c '%U %a' bootstrap/cache/config.php)"
expect 'every process runs as www-data' '33' "$(docker exec "$web" bash -c 'grep -h "^Uid:" /proc/[0-9]*/status 2>/dev/null | cut -f2 | sort -u | tr "\n" " " | sed "s/ $//"')"
expect 'it listens on 8080, and on the loopback address for the health check and PHP-FPM' \
    '00000000:1F90 0100007F:1F91 0100007F:2328' "$(listeners_of "$web")"

expect 'the sign-in page is served' '200' "$(request "$web" /login)"
expect_ok 'the page names its built assets' bash -c '[[ "$0" == */build/assets/* ]]' "$(body_of "$web")"
asset="$(body_of "$web" | grep -oE '/build/assets/[^"]+\.js' | head -n 1)"
expect 'a built asset is served' '200' "$(request "$web" "$asset")"
expect 'a static file carries the headers of SEC-HDR-02' 'x-content-type-options: nosniff' "$(header_of "$web" x-content-type-options | tr '[:upper:]' '[:lower:]')"
expect 'the specimen of the tests does not exist' '404' "$(request "$web" /testing/specimen -H 'Accept: application/json')"
expect 'no header names PHP' '' "$(header_of "$web" x-powered-by)"
expect 'the server header names no version' 'server: nginx' "$(header_of "$web" server | tr '[:upper:]' '[:lower:]')"

section 'Trusted hosts and proxies from the configuration'
expect 'another host name configured is answered' '200' \
    "$(docker exec "$web" curl -sS -o /dev/null -w '%{http_code}' -H "Host: $other_host" -H 'X-Forwarded-Proto: https' http://127.0.0.1:8080/up)"
expect 'an unknown host name is refused' '400' \
    "$(docker exec "$web" curl -sS -o /dev/null -w '%{http_code}' -H 'Host: other.invalid' -H 'X-Forwarded-Proto: https' http://127.0.0.1:8080/up)"
expect 'plain HTTP from the trusted hop goes to HTTPS' '308' \
    "$(docker exec "$web" curl -sS -o /dev/null -w '%{http_code}' -H "Host: $host" http://127.0.0.1:8080/up)"
sed 's#^APP_TRUSTED_PROXIES=.*#APP_TRUSTED_PROXIES=192.0.2.1/32#' "$web_env" >"$work/untrusted.env"
docker run -d --name "$untrusted" --network "$network" --env-file "$work/untrusted.env" "$image" >/dev/null
expect_ok 'a container with another proxy hop starts' wait_for_web "$untrusted"
expect 'a forwarded protocol from an address that is no hop is not believed' '308' "$(request "$untrusted" /up)"
expect_ok 'its health check does not depend on a hop' docker exec "$untrusted" /usr/local/bin/bantukasir-healthcheck
docker rm -f "$untrusted" >/dev/null 2>&1

# ------------------------------------------------------------------ 4. Private paths

section 'Private paths'
docker exec -u root "$web" bash -c '
    echo BK_SENTINEL > public/.sentinel
    echo "<?php echo \"BK_EXECUTED\";" > public/probe.php
    echo BK_SENTINEL > storage/app/private/media/sentinel
    echo BK_SENTINEL > .env.sentinel
    chown www-data:www-data public/.sentinel public/probe.php storage/app/private/media/sentinel'
for path in \
    /.sentinel /.env /.env.example /.env.sentinel /../.env /%2e%2e/.env /index.php/../.env /.git/config /.well-known/sentinel \
    /probe.php /index.php /artisan.php /index.php.bak \
    /vendor /vendor/autoload.php /vendor/composer/installed.json \
    /storage /storage/logs/laravel.log /storage/framework/sessions/ /storage/app/private/media/sentinel /storage/app/private/media/ \
    /bootstrap/cache/config.php /composer.json /composer.lock /package.json /artisan /app/Models/User.php \
    /config/app.php /routes/web.php /database/setup/setup.sql /tests/Harness/Fixtures/TokoContoh.php; do
    status="$(request "$web" "$path" -H 'Accept: application/json')"
    case "$status" in
        400 | 403 | 404) expect_refused "$path answered $status and shows nothing" grep -Eq 'BK_SENTINEL|BK_EXECUTED|APP_KEY|DB_PASSWORD|<\?php|"require"|\[core\]|CREATE ROLE' <<<"$(body_of "$web")" ;;
        *) fail "$path is unreachable: it answered $status" ;;
    esac
    # A path above the root is refused while the request line is parsed, before
    # any location with its headers (docker/app/nginx.conf).
    case "$path" in
        /../.env | /%2e%2e/.env) ;;
        *) expect "$path carries a header of SEC-HDR-02" 'x-frame-options: deny' "$(header_of "$web" x-frame-options | tr '[:upper:]' '[:lower:]')" ;;
    esac
done
expect 'the application cannot be reached by the listener of the health check, but for /up' '404' \
    "$(docker exec "$web" curl -sS -o /dev/null -w '%{http_code}' -H "Host: $host" http://127.0.0.1:8081/login)"

# ------------------------------------------------- 5. The body limits and the time limit

section 'The body limits of RT-12'
# post <path> <bytes>: a body of that size, as a client sends it.
post() {
    docker exec "$web" bash -c 'head -c "$0" /dev/zero > /tmp/check-payload' "$2"
    request "$web" "$1" -X POST -H 'Expect:' -H 'Accept: application/json' -H 'Content-Type: application/octet-stream' --data-binary @/tmp/check-payload
}
expect_body_limit() { # <route> <bytes> <description> <expected status> <expected body, or * for any>
    local answer
    answer="$(post "$1" "$2")"
    if [ "$5" = '*' ]; then
        case "$answer" in
            413 | 000) fail "$1 with $2 bytes $3: it answered $answer" ;;
            *) pass "$1 with $2 bytes $3: it answered $answer" ;;
        esac
    else
        expect "$1 with $2 bytes $3" "$4 $5" "$answer $(body_of "$web")"
    fi
}
# A JSON or form body: the application 1 MiB, PHP 1100K, Nginx 1200k.
expect_body_limit /login 1100000 'reaches the application' '' '*'
expect_body_limit /login 1180000 'is refused by PHP'\''s limit, through the application' 413 '{}'
expect_body_limit /login 1300000 'is refused by Nginx' 413 ''
expect_body_limit /up 1300000 'is refused by Nginx wherever it goes' 413 ''
expect 'the refusal of Nginx carries a header of SEC-HDR-02' 'x-content-type-options: nosniff' "$(header_of "$web" x-content-type-options | tr '[:upper:]' '[:lower:]')"
# RC-32: the application 5 MiB, PHP 7M, Nginx 8m.
expect_body_limit /commands/business_profile.change 6500000 'reaches the application' '' '*'
expect_body_limit /commands/business_profile.change 7800000 'is refused by PHP'\''s limit, through the application' 413 '{}'
expect_body_limit /commands/business_profile.change 8500000 'is refused by Nginx' 413 ''

section 'The 60-second termination, the limits per location and the received time'
cat >"$work/stub.php" <<'EOF'
<?php
// A stand-in front controller of the image checks, in place of public/index.php.
if (str_starts_with($_SERVER['REQUEST_URI'], '/slow')) {
    while (true) {
        sleep(1);
    }
}
header('Content-Type: text/plain');
echo implode(' ', [
    ini_get('post_max_size'),
    ini_get('upload_max_filesize'),
    isset($_SERVER['REQUEST_RECEIVED_AT']) && preg_match('/\A\d+\.\d{3}\z/', $_SERVER['REQUEST_RECEIVED_AT']) === 1 ? 'received' : 'no-received-time',
    ($_SERVER['HTTP_PROXY'] ?? '') === '' ? 'no-proxy' : 'proxy',
]);
EOF
chmod 644 "$work/stub.php"
docker run -d --name "$stub" --network "$network" --env-file "$web_env" -v "$work/stub.php:/var/www/html/public/index.php:ro" "$image" >/dev/null
expect_ok 'a container with the stand-in starts' wait_for_web "$stub"
for _ in 1 2 3; do
    expect 'a JSON route has the limits of RT-12 in PHP, the received time and no proxy from a header' '200 1100K 1M received no-proxy' \
        "$(request "$stub" /login -H 'Proxy: http://proxy.invalid') $(body_of "$stub")"
    expect 'RC-32 has its limits in PHP, whichever worker answers' '200 7M 6M received no-proxy' \
        "$(request "$stub" /commands/business_profile.change) $(body_of "$stub")"
done
started="$(date +%s)"
terminated="$(request "$stub" /slow)"
elapsed=$(($(date +%s) - started))
expect 'a request running past the limit is ended by a 502 of Nginx' '502' "$terminated"
expect 'that 502 names no server' '' "$(body_of "$stub")"
expect 'that 502 carries a header of SEC-HDR-02' 'x-frame-options: deny' "$(header_of "$stub" x-frame-options | tr '[:upper:]' '[:lower:]')"
# Below the client's wait of 65 seconds (TECHNICAL_RULES section 9; php-fpm.conf).
if [ "$elapsed" -ge 58 ] && [ "$elapsed" -le 64 ]; then pass "the request ended by the limit of 60 seconds, after $elapsed"; else fail "the request ended by the limit of 60 seconds: $elapsed"; fi
expect 'the next request is answered' '200' "$(request "$stub" /login)"
docker rm -f "$stub" >/dev/null 2>&1

# --------------------------------------------------------------------- 6. Role backup

section 'Role backup'
docker run -d --name "$backup" --network "$network" --env-file "$backup_env" "$image" >/dev/null
sleep 15
expect 'role backup keeps running' 'true' "$(docker inspect --format '{{.State.Running}}' "$backup")"
expect 'role backup publishes no port' '' "$(docker port "$backup")"
expect 'role backup listens on nothing' '' "$(listeners_of "$backup")"
expect 'role backup runs neither Nginx nor PHP-FPM' 'no-server' \
    "$(docker exec "$backup" bash -c 'grep -lE "^Name:\s+(nginx|php-fpm)" /proc/[0-9]*/status >/dev/null 2>&1 && echo server || echo no-server')"
docker exec "$backup" php artisan schedule:list --no-ansi >"$work/backup-schedule" 2>&1
web_commands="$(docker exec "$web" php artisan schedule:list --no-ansi 2>&1)"
cat "$work/backup-schedule"
echo "$web_commands"
expect_ok 'role backup registers SC-03' grep -q 'backup:run' "$work/backup-schedule"
expect_refused 'role backup registers nothing else' grep -Eq 'reconciliation:run|housekeeping:run|monitor:heartbeat' "$work/backup-schedule"
expect 'role web registers SC-01, SC-02 and SC-04' '3' "$(grep -cE 'reconciliation:run|housekeeping:run|monitor:heartbeat' <<<"$web_commands")"
expect_refused 'role web does not register SC-03' grep -q 'backup:run' <<<"$web_commands"
expect_ok 'the scheduler of role backup runs without a runtime credential' docker exec "$backup" php artisan schedule:run --no-ansi
expect_ok 'the scheduler of role web runs' docker exec "$web" php artisan schedule:run --no-ansi
expect_ok 'the health check of role backup passes' docker exec "$backup" /usr/local/bin/bantukasir-healthcheck
expect_ok 'pg_dump of role backup reaches the server, with row security' \
    docker exec -e PGPASSWORD="$backup_password" "$backup" pg_dump --enable-row-security --schema-only -h "$database" -U image_backup -d "$database_name" -f /tmp/check-dump
expect_ok 'age of role backup encrypts to the recipient' docker exec "$backup" bash -c 'echo probe | age -r "$BACKUP_RECIPIENT" -o /tmp/check-age'
expect_refused 'role backup holds no key of the application' docker exec "$backup" bash -c '[ -n "${APP_KEY:-}" ]'

# ------------------------------------------------- 7. The refusal of each setting

section 'Each role refuses a missing or default setting and prints no value'
expect_ok 'the complete settings of role web pass' docker run --rm --network none --env-file "$web_env" "$image" php artisan settings:check
expect_ok 'the complete settings of role backup pass' docker run --rm --network none --env-file "$backup_env" "$image" php artisan settings:check

# expect_refusal <environment file> <description> <setting>: a start with these
# settings ends by itself with a failure, naming the setting and no value.
expect_refusal() {
    local status output="$work/refusal-output"
    timeout 60 docker run --name "$refusal" --network none --env-file "$1" "$image" >"$output" 2>&1 </dev/null
    status=$?
    docker rm -f "$refusal" >/dev/null 2>&1
    if [ "$status" -eq 0 ] || [ "$status" -eq 124 ]; then
        fail "$2: the container started"
    elif ! grep -q "^SETTING_REFUSED $3 " "$output"; then
        fail "$2: it does not name the setting"
        sed -E 's/=.*/=.../' "$output" | tail -n 5
    elif ! no_secret_in "$output"; then
        fail "$2: a value was printed"
    else
        pass "$2"
    fi
}

for role in web backup; do
    environment_file="$work/$role.env"
    docker run --rm "$image" php artisan settings:check --describe --role="$role" >"$work/describe-$role"
    expect_ok "role $role describes its settings" grep -q '^required ' "$work/describe-$role"

    while read -r kind name value; do
        variant="$work/variant.env"
        grep -v "^$name=" "$environment_file" >"$variant"
        case "$kind" in
            required)
                expect_refusal "$variant" "role $role refuses $name missing" "$name"
                { grep -v "^$name=" "$environment_file"; echo "$name="; } >"$variant"
                expect_refusal "$variant" "role $role refuses $name empty" "$name"
                { grep -v "^$name=" "$environment_file"; echo "$name=changeme"; } >"$variant"
                expect_refusal "$variant" "role $role refuses $name holding a placeholder" "$name"
                ;;
            default)
                echo "$name=$value" >>"$variant"
                expect_refusal "$variant" "role $role refuses $name holding its default $value" "$name"
                ;;
            forbidden)
                echo "$name=held-by-another-role" >>"$variant"
                expect_refusal "$variant" "role $role refuses to hold $name" "$name"
                ;;
        esac
    done <"$work/describe-$role"

    grep -v '^APP_PROCESS_ROLE=' "$environment_file" >"$work/variant.env"
    expect_refusal "$work/variant.env" "a container without a role refuses to start" APP_PROCESS_ROLE
    { grep -v '^APP_PROCESS_ROLE=' "$environment_file"; echo 'APP_PROCESS_ROLE=worker'; } >"$work/variant.env"
    expect_refusal "$work/variant.env" "a container with another role refuses to start" APP_PROCESS_ROLE
done

section 'The logs of the running containers'
docker logs "$web" >"$work/web.log" 2>&1
docker logs "$backup" >"$work/backup.log" 2>&1
expect_ok 'the log of role web holds no secret' no_secret_in "$work/web.log"
expect_ok 'the log of role backup holds no secret' no_secret_in "$work/backup.log"

section 'A stop signal'
docker stop -t 8 "$web" >/dev/null
expect 'role web ends gracefully' '0' "$(docker inspect --format '{{.State.ExitCode}}' "$web")"
docker stop -t 8 "$backup" >/dev/null
expect 'role backup ends gracefully' '0' "$(docker inspect --format '{{.State.ExitCode}}' "$backup")"

# ---------------------------------------------------------------------------- 8. Scans

section 'The packages of the image'
docker run --rm "$image" dpkg-query -W -f='${Package} ${Version}\n'
docker run --rm "$image" php -r 'foreach (json_decode(file_get_contents("vendor/composer/installed.json"), true)["packages"] as $package) { echo $package["name"], " ", $package["version"], "\n"; }'

section 'The secret scan of the file system (Gitleaks)'
docker create --name "$rootfs_container" "$image" >/dev/null
docker export -o "$work/rootfs.tar" "$rootfs_container"
docker rm -f "$rootfs_container" >/dev/null 2>&1
mkdir "$work/rootfs" "$work/report"
tar -xf "$work/rootfs.tar" -C "$work/rootfs" --no-same-owner --exclude='dev/*' --exclude='proc/*' --exclude='sys/*' 2>/dev/null
rm -f "$work/rootfs.tar"
chmod -R a+rX "$work/rootfs" 2>/dev/null
chmod 777 "$work/report"
docker run --rm -v "$work/rootfs:/scan:ro" -v "$work/report:/report" -v "$root/scripts/checks/image-gitleaks.toml:/config.toml:ro" "$gitleaks_image" \
    dir --config /config.toml --no-banner --no-color --redact --report-format json --report-path /report/gitleaks.json /scan
scan_status=$?
if [ -f "$work/report/gitleaks.json" ]; then
    jq -r '.[] | "\(.RuleID) \(.File):\(.StartLine)"' "$work/report/gitleaks.json"
fi
if [ "$scan_status" -eq 0 ]; then pass 'the secret scan finds nothing'; else fail "the secret scan: status $scan_status"; fi
rm -rf "$work/rootfs" 2>/dev/null

section 'The vulnerability scan of the packages (Grype)'
expect_ok 'the gate of the scan refuses what it must' bash scripts/checks/image-scan-gate-test.sh
docker save -o "$work/image.tar" "$image"
chmod 644 "$work/image.tar"
docker run --rm -v "$work/image.tar:/image.tar:ro" "$grype_image" version 2>&1
if docker run --rm -v "$work/image.tar:/image.tar:ro" "$grype_image" -q -o json docker-archive:/image.tar >"$work/grype.json"; then
    jq -r '"Database: \(.descriptor.db.status.built // .descriptor.db.built // "unknown")"' "$work/grype.json"
    jq -r '.matches | group_by(.vulnerability.severity) | map("\(.[0].vulnerability.severity): \(length)") | .[]' "$work/grype.json"
    # A high or critical finding needs an accepted reason (contract section 10):
    # the gate prints every finding and refuses what D-112 does not cover
    # (scripts/checks/image-scan-gate.sh, scripts/checks/image-scan-accepted.json).
    expect_ok 'no high or critical finding is fixable, known to be exploited or without an accepted reason' \
        bash scripts/checks/image-scan-gate.sh "$work/grype.json" scripts/checks/image-scan-accepted.json
else
    fail 'the vulnerability scan ran'
fi
rm -f "$work/image.tar"

echo "::endgroup::"
echo "Image checks: $passes passed, $failures failed"
if [ "$failures" -gt 0 ]; then
    exit 1
fi
