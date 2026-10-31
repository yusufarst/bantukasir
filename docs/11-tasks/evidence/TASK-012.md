# TASK-012 — Evidence

| | |
| --- | --- |
| Task | [TASK-012](../tasks/TASK-012-application-image.md), Application image and runtime configuration; size M, review class B, lane O |
| Status | `DONE` when its pull request is squash-merged and CI passes on `develop` at the merge (EXECUTION_CONTEXT CE-50, CE-61); until then `develop` shows `READY`. The status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | D-101 as amended by D-104, D-105, D-107, D-108 and D-109, continuous execution (CE-01); mode `start` on 31 October 2026, after the tick verified TASK-011's merge; stop `OWNER_DECISION` at 04:39; mode `resume` from the phase `BUILD` at 07:34 with the Owner's decision D-112; stop `CHECKPOINT_LIMIT` at 08:47; mode `resume` from the phase `REVIEW` at 08:57 with the Owner's decision D-113 |
| Branch | `task/TASK-012-application-image`, created from `origin/develop` (CE-02) |
| Base commit | `d2ec7a67d021b2f4db09beb5ba9605bd6e6f7297`, the squash commit of pull request #18 (TASK-011) |
| Executor | `bk-executor-high` in Claude Code Desktop on the Owner's Windows machine, in the main working tree; Claude Opus 5.5 (`claude-opus-5-5`); effort High, from the table of EXECUTION_CONTEXT section 19 (CE-11, D-107) |
| Reviewer | `bk-reviewer-high` for the reviewer's check of class B and for a verification of fixes (CE-11, CE-40); section 8 |
| Gates | None of its own; EXECUTOR, checked at the start |
| Authorized beyond local commits | By CE-01 and D-105, for this Task only: its branch, commits and pushes through the scripts, one pull request into `develop` and its CI, the fixes of CE-40 to CE-43, and the tick's squash merge where CE-60 allows it. No staging or production (D-112) |

Times are Asia/Jakarta. Statuses use the vocabulary of [TEST_STRATEGY](../../08-testing/TEST_STRATEGY.md) section 8.

## 1. Start (EXC-20 to EXC-24, CE-20)

- **Model (CE-13).** `claude-opus-5-5`, the model that CE-11 names, in all three sessions.
- **Resume at 07:34 (CE-24).** The state held the phase `BUILD`, TASK-012, base `d2ec7a6`, no pull request; the working tree was clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, which CURRENT_HANDOFF section 2 accounts for; `git log` showed the four checkpoint commits of the state's `commit.approved`.
- **Resume at 08:57 (CE-24).** The state held the phase `REVIEW`, no pull request, the six checkpoint commits up to `022aa90`; the uncommitted changes were those that section 14 lists, and all were kept. The Owner's full text of D-113 reached the run after its start and replaces the short note of the resume.
- **Tools (D-109, CE-47).** Files were read with Read, Grep and Glob and changed only with Edit and Write; every Bash call was one allowed command. `jq` is absent on this machine, so the tests of the gate run in CI (section 9).
- **The previous merge (CE-50).** Pull request #18 is recorded in the [TASK-011 evidence](TASK-011.md) section 15 and in CURRENT_HANDOFF.

## 2. Versions and sources (EXC-03; contract section 8)

| Image | Digest | Used for |
| --- | --- | --- |
| `php:8.5.12-fpm-trixie` | `sha256:da5d8e27ec238adc0804fd4194f98e59477db7116690d275b76679c1b84c813f` | The runtime stage |
| `composer:2.10.3` | `sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac` | The production dependencies; Composer in the development stage |
| `node:24.22.0-trixie-slim` | `sha256:7f486cabe892bff8fef04c0f7da8dba84a560be3e4e2740b7a86afc57ebec315` | The Vite build |
| `golang:1.27.3-trixie` | `sha256:c65ecfe613b539ac74c62a4c3b2c27e8f8a6076384c8f27e8a3c0f3be17cc385` | age 1.3.2 from source |
| `ghcr.io/gitleaks/gitleaks:v8.30.1` | `sha256:c00b6bd0aeb3071cbcb79009cb16a60dd9e0a7c60e2be9ab65d25e6bc8abbb7f` | The secret scan; Gitleaks in the development stage |
| `postgres:18.6-trixie` | `sha256:74935e72241653ca55e0414067e6d8763aceb8a810eb51b452253ec3dcfc4336` | The ephemeral database of the checks |
| `anchore/grype:v0.121.0` | `sha256:621d56d1d68106b787022c81546f7e0a36202a25d1239f0c20195fab8c16f7ea` | The vulnerability scan |

| Scan tool | Version | Licence | Result on `022aa90`, run 41153155417 |
| --- | --- | --- | --- |
| Gitleaks | 8.30.1, default rules with the allowlist of `scripts/checks/image-gitleaks.toml` (two third-party files, its reasons there) | MIT | Nothing found on the image's file system |
| Grype, with Syft | 0.121.0, with 1.55.0; database built 2026-10-30T06:23:28Z | Apache-2.0 | Critical 13, High 99, Medium 61, Low 11, Negligible 96, Unknown 24, 304 findings, all printed in the run's log; the gate: 112 accepted, 0 refused, 192 of lower severity reported; none known to be exploited |

Image of that run: identifier `sha256:00074cb6248a41955ee72076aaf84ba6464592dd4bf96a847354323795613823`, 573,500,173 bytes. The digests above are in the definition; the image is built by CI and published by no job.

### 2.1 The image's packages (AC-012-01)

Debian (`dpkg-query`): adduser 3.152, apt 3.0.3, base-files 13.8+deb13u7, base-passwd 3.6.7, bash 5.2.37-2+b10, bsdutils 1:2.41.5-0+deb13u1, ca-certificates 20250419+deb13u1, coreutils 9.7-3, curl 8.14.1-2+deb13u7, dash 0.5.12-12, debconf 1.5.91, debian-archive-keyring 2025.1, debianutils 5.23.2, diffutils 1:3.10-4, dpkg 1.22.22, findutils 4.10.0-3, gcc-14-base 14.2.0-19, grep 3.11-4, gzip 1.13-1+deb13u1, hostname 3.25, init-system-helpers 1.69~deb13u1, libacl1 2.3.2-2+b1, libapt-pkg7.0 3.0.3, libargon2-1 0~20190702+dfsg-4+b2, libattr1 1:2.5.2-3, libaudit-common 1:4.0.2-2+deb13u1, libaudit1 1:4.0.2-2+deb13u1, libblkid1 2.41.5-0+deb13u1, libbrotli1 1.1.0-2+deb13u1, libbsd0 0.12.2-2, libbz2-1.0 1.0.8-6, libc-bin 2.41-12+deb13u4, libc6 2.41-12+deb13u4, libcap-ng0 0.8.5-4+b1, libcap2 1:2.75-10+deb13u1+b3, libcom-err2 1.47.2-3+b12, libcrypt1 1:4.4.38-1, libcurl4t64 8.14.1-2+deb13u7, libdb5.3t64 5.3.28+dfsg2-9, libdebconfclient0 0.280, libffi8 3.4.8-2, libgcc-s1 14.2.0-19, libgdbm-compat4t64 1.24-2, libgdbm6t64 1.24-2, libgmp10 2:6.3.0+dfsg-3, libgnutls30t64 3.8.9-3+deb13u4, libgssapi-krb5-2 1.21.3-5+deb13u1, libhogweed6t64 3.10.1-1, libidn2-0 2.3.8-2, libjpeg62-turbo 1:2.1.5-4, libk5crypto3 1.21.3-5+deb13u1, libkeyutils1 1.6.3-6, libkrb5-3 1.21.3-5+deb13u1, libkrb5support0 1.21.3-5+deb13u1, liblastlog2-2 2.41.5-0+deb13u1, libldap2 2.6.10+dfsg-1, liblz4-1 1.10.0-4, liblzma5 5.8.1-1+deb13u2, libmd0 1.1.0-2+b1, libmount1 2.41.5-0+deb13u1, libnettle8t64 3.10.1-1, libnghttp2-14 1.64.0-1.1+deb13u1, libnghttp3-9 1.8.0-1, libonig5 6.9.9-1+b1, libp11-kit0 0.25.5-3, libpam-modules 1.7.0-5, libpam-modules-bin 1.7.0-5, libpam-runtime 1.7.0-5, libpam0g 1.7.0-5, libpcre2-8-0 10.46-1~deb13u3, libperl5.40 5.40.1-6+deb13u1, libpng16-16t64 1.6.48-1+deb13u6, libpq5 18.6-1.pgdg13+2, libpsl5t64 0.21.2-1.1+b1, libreadline8t64 8.2-6, librtmp1 2.4+20151223.gitfa8646d.1-2+b5, libsasl2-2 2.1.28+dfsg1-9, libsasl2-modules-db 2.1.28+dfsg1-9, libseccomp2 2.6.0-2, libselinux1 3.8.1-1, libsemanage-common 3.8.1-1, libsemanage2 3.8.1-1, libsepol2 3.8.1-1, libsharpyuv0 1.5.0-0.1, libsmartcols1 2.41.5-0+deb13u1, libsodium23 1.0.18-1+deb13u1, libsqlite3-0 3.46.1-7+deb13u2, libssh2-1t64 1.11.1-1+deb13u2, libssl3t64 3.5.7-1~deb13u3, libstdc++6 14.2.0-19, libsystemd0 257.13-1~deb13u1, libtasn1-6 4.20.0-2+deb13u1, libtinfo6 6.5+20250216-2, libudev1 257.13-1~deb13u1, libunistring5 1.3-2, libuuid1 2.41.5-0+deb13u1, libwebp7 1.5.0-0.1, libxml2 2.12.7+dfsg+really2.9.14-2.1+deb13u3, libxxhash0 0.8.3-2, libzstd1 1.5.7+dfsg-1, login 1:4.16.0-2+really2.41.5-0+deb13u1, login.defs 1:4.17.4-2, mawk 1.3.4.20250131-1, mount 2.41.5-0+deb13u1, ncurses-base 6.5+20250216-2, ncurses-bin 6.5+20250216-2, netbase 6.5, nginx 1.30.6-1~trixie, openssl 3.5.7-1~deb13u3, openssl-provider-legacy 3.5.7-1~deb13u3, passwd 1:4.17.4-2, perl 5.40.1-6+deb13u1, perl-base 5.40.1-6+deb13u1, perl-modules-5.40 5.40.1-6+deb13u1, postgresql-client-18 18.6-1.pgdg13+2, postgresql-client-common 293.pgdg13+1, readline-common 8.2-6, sed 4.9-2+deb13u1, sensible-utils 0.0.25, sqv 1.3.0-3+b2, sysvinit-utils 3.14-4, tar 1.35+dfsg-3.1, tzdata 2026f-0+deb13u1, util-linux 2.41.5-0+deb13u1, zlib1g 1:1.3.dfsg+really1.3.1-1+b1.

Composer: the production set of `composer.lock`, printed by each run from `vendor/composer/installed.json`, 95 packages; no `dev` package, as the checks of section 7 prove. Their versions are those of `composer.lock` and of TOOLCHAIN section 1.1. Outside a package manager: PHP 8.5.12 of the base image, age 1.3.2 of the `age` stage.

## 3. What was built (contract section 4.1)

| Scope item | Where |
| --- | --- |
| 1, the image | `docker/app/Dockerfile` (stages `runtime`, `release`, `development`), `.dockerignore` |
| 2, process roles | `APP_PROCESS_ROLE`; `App\Console\ProcessSchedule` with `bootstrap/app.php`; the role's database connection and cache store in `config/`; `docker/app/entrypoint.sh` |
| 3, the start-up check | `App\Domain\Operations\RequiredSettings`, `php artisan settings:check`, `docker/app/entrypoint.sh`; configuration, routes and views cached at start; no migration |
| 4, Nginx and PHP-FPM | `docker/app/nginx.conf`, `nginx-front-controller.conf`, `nginx-security-headers.conf`, `php-fpm.conf`, `php.ini`, `serve.sh`, `healthcheck.sh` |
| 5, trusted proxies and hosts | Already read from configuration (TASK-007); proven on the image by the checks, and `APP_TRUSTED_PROXIES` is required in role `web` |
| 6, the image checks | `scripts/checks/image-checks.sh`, `image-gitleaks.toml`, `image-scan-gate.sh`, `image-scan-gate-test.sh`, `image-scan-accepted.json`; the job `Image checks` of `.github/workflows/ci.yml` |
| 7, the local environment | `compose.yaml` builds the stage `development` of the one definition, with `docker/app/entrypoint-local.sh` |

Documents: TOOLCHAIN, ENVIRONMENTS sections 6 and 11, `.env.example`, RELEASE_PROCESS sections 4 and 12, OPEN_ITEMS T-28 and T-38, and the contract of TASK-013 (D-112).

## 4. Readings and technical choices

| Subject | Choice |
| --- | --- |
| One runtime | Local and CI use the stage `development`, which adds Composer, Git and Gitleaks to `runtime` and bind-mounts the repository as root, because the files belong to the host's user; the release adds the application to the same `runtime` and runs as `www-data`. |
| Debian packages | `apt-get upgrade` in the build: the digest pins the base, and the archive gives security updates at the time of the build; Nginx is pinned by version and the client tools by major version; the rebuild check compares packages and files. |
| PHP | 8.5.12, because the scan reported fixes in 8.5.12; `composer.json` keeps 8.5.11, as TOOLCHAIN says. |
| age | Built from source with the current Go, because Debian's build with Go 1.24.4 carried fixable findings. |
| Time limit | `request_terminate_timeout` of 60 seconds. PHP-FPM's master looks at its workers a third of the smallest limit apart, 20 seconds with this limit alone, which measured 78 seconds, past the client's wait of 65; the empty pool `limit-check` of 3 seconds makes it one second: measured 61 seconds. The 502 that ends a request is Nginx's. |
| Body limits | JSON and form: application 1 MiB, PHP 1100K, Nginx 1200k. RC-32: application 5 MiB, PHP 7M, Nginx 8m, by an exact location with `PHP_VALUE`. RC-25 and RR-01 do not exist yet (finding F-1). |
| Health check | A second listener on the loopback address, for `/up` only, because a plain request from the loopback address is no trusted hop (TASK-007 F-1); `/up` answers in maintenance mode too (TASK-007 F-11, RH-01). |
| Role `backup` | Default connection `pgsql_backup` and a file cache for the scheduler's lock, because that role holds no other credential; its process waits for a signal and only `schedule:run` works. |
| Notes taken from earlier Tasks | TASK-007 F-1 and F-11; TASK-008 F-2 (`REQUEST_RECEIVED_AT`, checked on the image); TASK-010 F-2 (GD with PNG, JPEG, WebP), F-3 (RC-32's limits), F-5 (`ini_set` of `memory_limit`, 384M checked) and F-8 (the media folder's owner). |
| Worker pool (TR-TIME-03, OPEN_ITEMS T-28) | `pm.max_children = 8`, an assumption stated in `php-fpm.conf` that TASK-048 measures. |

## 5. The scan's findings and D-112

The contract stopped the Task on 43 high or critical CVEs (112 findings) of run 41129923061, whose image identifier was `sha256:60f08d3557e6e6b3d01cbad903b8fdf11a8e13ca7a4bd9f2b9fded719e4e5b4e`. The Owner's decision and its limitations are in [DECISIONS](../../00-governance/DECISIONS.md) D-112. The record of each CVE — packages and versions, fix state, Debian's status, rationale, mitigation, review trigger — is `scripts/checks/image-scan-accepted.json`. Every status below was read on 31 October 2026 from Debian's security tracker, the curl project's advisories, CISA's `cisagov/vulnrichment` and NVD's API. "Limited to the assessed image" reads: its packages at these exact versions, which the gate compares; an image of a later build is the same while they hold.

### 5.1 Each critical finding

The scanner's severity of the six curl CVEs comes from CISA's automated scoring, whose SSVC record states a proof of concept for each; the curl project's advisories rate them Low or Medium. None is in Grype's data of CISA's known-exploited catalogue at runs 41152371532 and 41153155417, and none has a `kev` entry in CISA's Vulnrichment record, the source of the SSVC column.

| CVE | Scan severity from | Upstream severity | SSVC exploitation, automatable | EPSS | Debian trixie | Needs | Exposure in this image |
| --- | --- | --- | --- | --- | --- | --- | --- |
| CVE-2026-6653 (libxml2) | NVD's primary CVSS 3.1 9.8; the CNA gives CVSS 4.0 7.0, with E:P in its vector | CNA: High, attack complexity High | none, no | 0.00355 | vulnerable, `<no-dsa>` (Minor issue) | The XML parser on crafted input with faulty entity resolution | Not reachable: see below |
| CVE-2026-10536 (curl) | CISA 9.8 | Low; libcurl only | poc, yes | 0.00598 | vulnerable, `<no-dsa>` (Minor issue) | HTTP/2 stream dependencies, then reset and cleanup | Not reachable: no stream dependency is set anywhere |
| CVE-2026-11856 (curl) | CISA 9.8 | Medium; libcurl only | poc, yes | 0.00688 | same | Digest authentication with a handle reused across origins | Not reachable: no Digest authentication is configured |
| CVE-2026-18924 (curl) | CISA 9.1 | Low; libcurl only | poc, yes | 0.00584 | same | Five conditions, among them a push callback that accepts HTTP/2 Server Push | Not reachable: no push callback or shared connections |
| CVE-2026-19931 (curl) | CISA 9.8 | Medium; libcurl and the tool | poc, yes | 0.01128 | same | Negotiate with blank credentials and a reused connection | Not reachable: no Negotiate; the health check sends no credentials |
| CVE-2026-8926 (curl) | CISA 9.1 | Low; libcurl and the tool | poc, no | 0.00444 | same | A `.netrc` with another user's entry, and a URL with a user name only | Not reachable: no `.netrc`, none configured |
| CVE-2026-9079 (curl) | CISA 9.8 | Medium; libcurl only | poc, yes | 0.01064 | same | A handle told to clear proxy credentials, then reused | Not reachable: no proxy credentials exist |

What libxml2 does here. No route accepts XML, no code of `app/` calls a parser, and GD reads images. One call parses: `nunomaduro/termwind`, a dependency of the framework, calls `DOMDocument::loadHTML` (`HtmlRenderer.php`) for the console components (`Illuminate\Console\View\Components\Component`), at each container start (`config:cache`, `route:cache`, `view:cache`) and in `schedule:run`, in both roles, in the migration job (the migrator's lines) and in the framework's operator commands. Its input at this revision is the framework's fixed text and the schedule's identifiers, the content through `htmlspecialchars` in the component's view (`line.php`); the application's own commands print through `$this->info`, `$this->error` and `$this->line`, which do not use Termwind. That is the HTML parser: `htmlParseDocTypeDecl` hands the doctype to the SAX callback, and `HTMLparser.c` of 2.9.14 does not call `xmlParseInternalSubset`, fetched whole from GNOME's repository at tag `v2.9.14`, which Debian's package version names; Debian's own patches were not read. Other vendor parsers run on no input from a request: `symfony/serializer` (no use in `app/` or Laravel), `css-to-inline-styles` (mail, and no mail transport exists), `symfony/console`'s `XmlDescriptor` and `symfony/translation`'s dumpers (operator commands). The Task that prints request-derived or stored text through a component reassesses this (T-38 (c)).

What libcurl does here: `grep` of `app/`, `bootstrap/`, `config/`, `routes/` and `database/` finds no `curl_*`, `Http::`, Guzzle or remote `file_get_contents`, and nothing else in the image's use starts a transfer but the health check: `curl -fsS -o /dev/null --max-time 10 -H "Host: …" http://127.0.0.1:8081/up` (`docker/app/healthcheck.sh`), plain HTTP to its own loopback listener, without credentials, cookies, a proxy or TLS. PHP's `curl` extension stays loaded, as the official image builds it, so that PHP links libcurl.

Assessment: none of the seven is materially reachable, known to be exploited, or left unassessed, so the stop of D-112's condition 2 does not hold. This is a judgment from reading the code and the upstream advisories; no exploit was attempted.

### 5.2 The high findings, by source package

| Source | CVEs (High) | Status | Why it does not bear on this image |
| --- | --- | --- | --- |
| curl, 13 | CVE-2026-12064, -13608, -80229, -80230, -80255, -82209, -86409, -86410, -8932, -9080, -92386, -92387, -9545 | vulnerable, `<no-dsa>` | The same: no libcurl transfer but the health check's. TASK-013 reassesses them; CVE-2026-86409 (SigV4), -80229 (pooled transfers) and -8932 (connection reuse) bear on it most |
| libxml2, 7 | CVE-2026-74860, -86138, -86139, -86140, -86142, -86143, -86144 | vulnerable, no note for trixie | No route accepts XML; the one parse is Termwind's, of fixed console text, above; -74860 needs Python, and the image holds none |
| util-linux, 4 | CVE-2026-76642, -78408, -78409, -78410 | vulnerable, `<no-dsa>` (Minor issue) | Need code execution in the container first and a run of `mount` or `nsenter`: no route reaches a shell or an external program, and nothing starts either. The container's capabilities are for gate TARGET |
| glibc, 2 | CVE-2026-19499, -5435 | vulnerable, `<no-dsa>` | `strfmon` and the deprecated `ns_printrr` family, which nothing here calls |
| gcc-14, 2 | CVE-2026-102010, -95619 | vulnerable, `<no-dsa>` (Minor issue) | libstdc++ functions a C++ program must call on attacker sizes; the project has no C++ code |
| perl, 2 | CVE-2026-82560, -9538 | vulnerable, `<no-dsa>` | `Pod::Text` and `Archive::Tar`; Perl runs only the client tools' wrappers |
| acl, 2 | CVE-2026-54369, -54370 | vulnerable, `<no-dsa>` | The acl tools, whose package is not installed |
| krb5, 1 | CVE-2026-107778 | vulnerable, `<no-dsa>` (Minor issue) | Crashes a GSS-API acceptor; there is none |
| cyrus-sasl2, 1 | CVE-2026-107161 | vulnerable, `<no-dsa>`, "Will be fixed via point release" | The DIGEST-MD5 plugin of `libsasl2-modules`, not installed |
| ncurses, 1 | CVE-2025-69720 | vulnerable, `<no-dsa>` (Minor issue) | The `infocmp` tool, which nothing runs |
| zlib, 1 | CVE-2026-85091 | vulnerable, no note for trixie; the description names 1.3.1.2 to 1.3.2, this is 1.3.1 | `gzprintf` after a non-blocking stall, which nothing here does |

### 5.3 Updates: one exists, partial and not upgraded automatically (D-113)

- **Selected runtime.** Debian 13 with `php:8.5.12-fpm-trixie`; PHP 8.5 is in active support. `apt-get upgrade` runs in the build, and each listed version equals the newest that Debian's tracker lists for trixie or trixie-security.
- **What cannot be removed.** libcurl and libxml2 are linked by PHP as the official image builds it; glibc, util-linux, gcc's runtime, acl, ncurses and zlib belong to the base; krb5 and cyrus-sasl come with libpq and libcurl; perl with the client tools. Removing the `curl` command alone leaves the libcurl findings.
- **One partial update exists**, and by D-113 TASK-012 does not upgrade to it automatically (finding F-6). The record that D-113 asks for follows. The versions below are the curl project's upstream ones, from `curl.se/docs/vuln-8.21.0.html` and `vuln-8.14.1.html`, read on 31 October 2026; Debian's own patches to either package were not read, and no build with this curl was made or scanned.
- **Another distribution** is another runtime, outside the selected one; not assessed.

| Point | Record |
| --- | --- |
| Availability | `packages.debian.org` lists curl `8.21.0-2~bpo13+1` in `trixie-backports`; libxml2 has none. The image stays on `8.14.1-2+deb13u7` |
| Security benefits | 8 of the list's 19 curl findings do not affect 8.21.0: 4 of 6 critical (CVE-2026-10536, -11856, -8926, -9079) and 4 of 13 high (CVE-2026-12064, -8932, -9080, -9545). Upstream lists 33 CVEs against 8.21.0 and 60 against 8.14.1 |
| Remaining vulnerabilities | 11 of the 19 still affect 8.21.0: 2 critical (CVE-2026-18924, -19931) and 9 high (CVE-2026-13608, -80229, -80230, -80255, -82209, -86409, -86410, -92386, -92387). Six of the 33 are not listed against 8.14.1, so that the update would add them: CVE-2026-94265, -86415, -86413, -86412, -86411, -87861. CVE-2026-92392, marked `H` on the page of 8.21.0 and also listed against 8.14.1, is not in the accepted list, and the gate passed, so run 41153155417 did not report it as high or critical on this image; why was not determined. Every finding of the other source packages stays |
| Support tradeoffs | `backports.debian.org` calls backports best-effort, and Debian's tracker lists none for these CVEs, so the package would leave the stable release's security process. It adds a package source to the build and pins a version that moves on its own schedule. Whether Grype would stop reporting the closed findings was not determined |
| Why not now | Nothing but the health check's fixed loopback request uses libcurl until TASK-013, so the benefit is small today and the tradeoffs are not |
| What follows | TASK-013 reassesses before its HTTP or S3 integration (its scope item 9, AC-013-09), and gate RELEASE reassesses again (RELEASE_PROCESS section 12, T-38 (a)). This is no production risk acceptance (D-113) |

### 5.4 The gate

`scripts/checks/image-scan-gate.sh` carries out conditions 3 and 4 of D-112, with the cases of `image-scan-gate-test.sh`, which run in the job before the scan. It runs in the job `Image checks`, and a merge needs every check run of a head to pass (`scripts/executor/lib.sh`), so each later head is held to it, with whatever Grype's database says that day (OPEN_ITEMS T-38 (d)).

## 6. Findings

| ID | Finding | Outcome |
| --- | --- | --- |
| F-1 | RT-12 also names RC-25 (8 MiB) and RR-01 (2 MiB), whose routes do not exist yet. | `nginx.conf` says how: an exact location per route. TASK-018 and the Task of RR-01 add theirs, and TASK-018 raises `php.ini` for photos |
| F-2 | The base image declares port 9000, which an image cannot remove. | PHP-FPM listens on the loopback address only; the checks expect exactly that declaration and those listeners; role `backup` listens on nothing and publishes nothing |
| F-3 | Checkpoint 4's command contract failed: the pool `limit-check` of `3d4eacf` had no user, which ends a PHP-FPM master running as root, as in the local environment, and only there. | Fixed with `user` and `group`; the regression check starts PHP-FPM as root in the image checks; proven by checkpoint 5 |
| F-4 | A red scan can come from Grype's database alone, on a head that changed nothing of the image. | Required by D-112; OPEN_ITEMS T-38 (d). The Owner should expect this stop from time to time |
| F-5 | `jq` is absent on the Owner's machine. | The gate and its tests ran in CI only (section 9); their local attempt is not evidence |
| F-6 | The partial update of curl in `trixie-backports` (section 5.3). | Not upgraded automatically, by D-113; reassessed in TASK-013 and again at gate RELEASE (T-38 (a)); stated in the report and the pull request's description |
| F-7 | `App\Http\Controllers\Testing\SpecimenController` is in the image (review R-08). `routes/web.php` names it and turns its route off outside `local` and `testing`, as the image checks prove with a 404. | A dormant class, recorded; removing the file would break route loading, and moving the gate out of it is for a later Task |
| F-8 | The rebuild's check compares packages and files under `/var/www/html` minutes apart in one job (review R-11). With `apt-get upgrade` unpinned it proves no reproducibility over time, and it does not compare age or `/etc`. | A limit of AC-012-01, stated there; the digests pin the bases, and each run prints its packages |
| F-9 | The 192 findings of lower severity exist in the run's log only, which CI expires (review R-13 (e)). | Their counts are recorded here; each run prints them again. A CI artifact is for gate RELEASE if it wants one |

## 7. Tests

| Case | Where |
| --- | --- |
| Pinned digests, the release's start never migrates, the build, and a rebuild without a cache: same packages and files, 7871 entries | `image-checks.sh`, sections 1 and the rebuild |
| No test folder, `node_modules`, `.env`, documents, Composer, compiler or Git in the image; no `dev` package; no specimen in the build; the runtime user cannot change the application; GD, `pdo_pgsql`, `memory_limit`, client tools 18, age | Section 2 |
| Role `web`: starts with an empty database and migrates nothing; 503 before the migrations, with an empty body; holds no migration credential; the migration job as an explicit command; `/up` 200; caches; every process `www-data`; listeners; assets; headers; no specimen | Section 3 |
| Trusted hosts and hops read from configuration | Section 3 |
| 31 private paths and dotfiles, each refused without its content, with the headers of SEC-HDR-02 | Section 4 |
| Body limits at Nginx, PHP and the application; PHP's limits per location through every worker; the 60-second termination, its 502 without a server's name; the next request answered | Section 5 |
| Role `backup`: runs, publishes and listens to nothing, no Nginx or PHP-FPM; only SC-03; web only SC-01, SC-02, SC-04; `pg_dump --enable-row-security` and age work; no application key | Section 6 |
| Each role refuses every required setting missing, empty or a placeholder, every default and every credential of another role; a wrong or absent role; no value printed; no secret in the logs; a stop signal ends gracefully | Section 7 |
| The secret scan, the vulnerability scan and the gate's cases (41 on `022aa90`, 49 with the review's fixes) | Section 8 |
| `RequiredSettingsTest`, `CheckSettingsTest`, `ProcessScheduleTest`, `HealthRouteTest` (maintenance mode) | PHP suites |

The standing obligations of EXECUTION_CONTEXT section 10: no route, table, command, read, page, procedure or document of a route is added; `settings:check` is an operator-side check of the start.

## 8. Self-review and review (CE-40)

Self-review of the whole change against the contract: on `022aa90`, nothing further raised beyond F-1 to F-6.

The reviewer's check by `bk-reviewer-high`, at effort High, round 1, package `review/TASK-012/r1` of the executor state, on `022aa90` with the uncommitted record: FAIL with three MUST-FIX. The working tree and the state were unchanged after it (`review-pack.sh --check`: `UNCHANGED`).

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | MUST-FIX | An empty report, or one without an image and Debian, passed the gate: a silent pass of a scan that catalogued nothing | Fixed: `SCAN_GATE_UNUSABLE`, status 2, with five new cases in `image-scan-gate-test.sh`; the list's check now uses a non-empty report. Field names read from Grype 0.121.0's `document.go`, `source.go` and `distribution.go` |
| R-02 | MUST-FIX | A failed `docker export` or `tar` left the secret scan on an empty or partial folder, which passes | Fixed: `image-checks.sh` checks both, and four known files, and does not scan otherwise; its failure branches are proven by reading only, no failure is injected, and each run proves its positive path |
| R-03 | MUST-FIX | The record said that no XML or HTML parse runs, and omitted Termwind's `loadHTML`, reached at each start and in `schedule:run` | Fixed: section 5.1, the list's libxml2 entries, D-112's limitations and T-38 (c), each stating what feeds it; the conclusion "not materially reachable" stays, on fixed text and the HTML parser |
| R-04 | SHOULD-FIX | A finding without a severity passed as lower severity, even with a fix | Fixed: unusable, with two cases |
| R-05 | SHOULD-FIX | The mitigation of CVE-2026-76642 presented `www-data` as the limit of a bug that needs such a user | Fixed in the list and section 5.2; the capabilities for gate TARGET |
| R-06 | SHOULD-FIX | `database/setup/selftest.sh`, `local.sh` and `state.sql` shipped in the image, outside the fixture check | Fixed: `.dockerignore`, and the check lists them. Nothing of the image needs them; `setup.sql` stays |
| R-07 | SHOULD-FIX | The record did not name the score behind CVE-2026-6653's Critical | Fixed: NVD's primary CVSS 3.1 9.8, read from its API |
| R-08 | NOTE | The specimen's controller is in the image, dormant | F-7 |
| R-09 | NOTE | A known-exploited finding below High passes as reported; the fix state of an entry is not compared; RELEASE_PROCESS omitted "unknown fix state" | The first two as D-112 words them, for gate RELEASE; RELEASE_PROCESS corrected |
| R-10 | NOTE | The heading of section 5.3 was stronger than its content; the high curl CVEs that 8.21.0 closes are not counted | Heading changed, and the count, not determined then, read from the curl project's version pages for D-113 (section 5.3); F-6 goes in the pull request's description |
| R-11 | NOTE | The limit of the rebuild's check | F-8 |
| R-12 | NOTE | `ProcessScheduleTest` asserts a time zone it passed itself | Recorded, not changed: `config/app.php` sets it and the checks print it; for the Task that next changes the schedule |
| R-13 | NOTE | (a) TASK_PLAN on T-28; (b) circular pointers; (c) a PASS ahead of its result; (d) "assessed image" unstated; (e) a CI log that expires | (a) to (d) corrected; (e) F-9 |
| R-14 | NOTE | The five checkpoint runs are used and `jq` is absent, so the fixes of R-01, R-02, R-04 and R-06 have no permitted run | The stop of section 14, answered by D-113 |

The targeted check by `bk-reviewer-high`, mode verify, at effort High, round 2, package `review/TASK-012/r2`, by reading, because the fixes have no run yet (section 9): PASS, with R-01 to R-07 FIXED and none REGRESSED; `UNCHANGED` after it. Its new findings were fixed after it, in rare paths and in the same files; no further targeted check is needed, for no MUST-FIX is open.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-15 | SHOULD-FIX | A report naming Debian with no `deb` package and another finding would pass | Fixed: unusable, with one case; 49 in all |
| R-16 | SHOULD-FIX | Unanchored `--exclude` of `dev/*`, `proc/*`, `sys/*` could drop a folder of those names at any depth, against "arrives whole" | Fixed: `--anchored` |
| R-17 | SHOULD-FIX | Termwind's path also runs in the migration job and the framework's operator commands; the application's commands also print through `$this->line` | Fixed in section 5.1, the list and D-112's limitations |
| R-18 | SHOULD-FIX | CVE-2026-78409 and -78410 still presented `www-data` as a limit | Fixed in the list |
| R-19 | NOTE | The counts of cases in the record; no injected failure of R-02's branches | Corrected and stated in the table above |

## 9. Checks (CE-44 to CE-46, D-108)

The five checkpoint runs of CE-46 are used, and by D-113 there is no further one: CI on the pull request's final head is the first run of the review's remaining fixes. The results of run 41153155417 stand for all but what those fixes touch (CE-45): `image-checks.sh`, the gate, its tests and `.dockerignore`. The job `Image checks` is `NOT_RUN` on those fixes until that run. The command contract, which reads none of them but `.dockerignore`, whose lines concern only the release's stage, stays valid, and runs again on that head anyway (CE-41).

| Check | Where it ran | Date and time | Duration | Result |
| --- | --- | --- | --- | --- |
| Command contract, image checks | CI 41125576447, `d03a329` | 31 Oct 03:44 | 49 s, 52 s | FAIL: the build (a diverted file) |
| Command contract | CI 41126102477, `88d4171` | 31 Oct 03:48 | 21 min 19 s | PASS |
| Image checks | CI 41126102477, `88d4171` | 31 Oct 03:48 | 6 min 24 s | FAIL: ten readings and limits, fixed in `ef64c1a` |
| Command contract | CI 41129923061, `ef64c1a` | 31 Oct 04:17 | 20 min 35 s | PASS |
| Image checks | CI 41129923061, `ef64c1a` | 31 Oct 04:17 | 6 min 39 s | 264 passed, 1 FAIL: the scan's findings (the stop) |
| Command contract | CI 41152371532, `29a41b7` | 31 Oct 07:50 | 1 min 10 s | FAIL: F-3 |
| Image checks | CI 41152371532, `29a41b7` | 31 Oct 07:50 | 6 min 20 s | PASS, 266 |
| Command contract: `composer validate`, lint, analyse, `test:unit` 579, `test:postgres` 396, `test:race` 29, `npm run lint`, typecheck, `test:unit` 524, build, browser 234 | CI 41153155417, `022aa90` | 31 Oct 08:00 | 19 min 27 s | PASS |
| Image checks, gate's tests 41 of 41 | CI 41153155417, `022aa90` | 31 Oct 08:00 | 6 min 0 s | PASS, 267 passed, 0 failed |
| Gate's tests, native | Local, no `jq` | 31 Oct | — | `NOT_RUN`: the tool is absent (F-5) |
| Image checks and the gate's tests with the review's fixes (49 cases; 268 checks expected) | CI on the pull request's head, not yet (D-113) | — | — | `NOT_RUN` |
| Command contract on the final head | CI on the pull request's head, not yet (CE-41) | — | — | `NOT_RUN` |
| Publication guard | Local, each push | 31 Oct | About a minute | PASS up to `022aa90`; one candidate accepted, section 13; the final head's result is in the report and the pull request's description |

Every run is on the branch `task/TASK-012-application-image`, with synthetic data on CI's Linux runner. A commit cannot name its own run, so the record of this merge by the next Task states the run of the final head, its 49 cases of `Scan gate tests` and its image checks (CE-50, D-113).

## 10. Acceptance criteria

| Criterion | Status | Evidence |
| --- | --- | --- |
| AC-012-01 | PASS on `022aa90`; `NOT_RUN` on the final head until CI there (D-113) | Digests and packages in section 2; the rebuild's check, within its limit (F-8); the scan with D-112's list (section 5) |
| AC-012-02 | PASS | Image checks, sections 3 and 6 |
| AC-012-03 | PASS | Image checks, section 7; `RequiredSettingsTest`; no start migrates (sections 1 and 3 of the checks) |
| AC-012-04 | PASS | Image checks, sections 4 and 5; 61 seconds measured; RC-25 and RR-01 for their Tasks (F-1) |
| AC-012-05 | PASS on `022aa90`; `NOT_RUN` on the final head until CI there (D-113) | Job `Image checks`, run 41153155417; its scan under the conditional acceptance of D-112 only |
| AC-012-06 | PASS | `compose.yaml`, target `development` of the one definition; run 41153155417 ran the whole command contract on it |

## 11. Definition of Done (EXECUTION_CONTEXT section 13)

| Dimension | Status | Reason |
| --- | --- | --- |
| Business | PASS | Section 10 |
| UI and UX | N/A | No page or control |
| Backend | PASS | No route or command added; `RequiredSettings`, `settings:check` and `ProcessSchedule` with their tests; the route of RH-01 in maintenance mode |
| Database | N/A | No migration; the setup script runs unchanged |
| Security | PASS | THR-15 and SEC-UP-02 proven on the image; SEC-SECRET-03 proven per setting; the scan under D-112, which authorizes no deployment |
| Data integrity | N/A | Writes no business fact |
| Performance | N/A | No request path; the pool is an assumption for TASK-048 |
| Operations | PASS | ENVIRONMENTS sections 6 and 11, RELEASE_PROCESS sections 4 and 12; no table for the backup manifest |
| Verification | `NOT_RUN` on the final tree | The command contract and the image checks green in checkpoint run 41153155417 on `022aa90`; the self-review; the reviewer's check and the targeted check, which read the MUST-FIX fixes (section 8). CI on the pull request's final head is the first run of the review's remaining fixes and the gate (CE-41, D-113) |

## 12. Backup impact (EXC-47)

None: no table is added. The role `backup` of TASK-013 runs in this image, with `pg_dump --enable-row-security` proven against the pinned server.

## 13. Guard (CE-31, CE-50)

| Where | Candidates | Judgment |
| --- | --- | --- |
| The guard on `29a41b7` | One, `ipv4|…|scripts/checks/image-scan-accepted.json|1.3.1.2` | A zlib version in a CVE's description, not an address; accepted by its hash |
| The guard on `022aa90` | None | PASS |

`guard.sh --preview` on the working tree, 31 October 2026, with the review's fixes and D-113, against `origin/develop`, 22 candidates, none a real finding: `1.3.1.2`, a zlib version, in this file and the list; `127.0.0.11`, Docker's resolver, `192.0.2.1` and `192.0.2.0`, documentation addresses, `10.0.0.1`, a private address of a test; `127.0.0.1` with ports 8080 and 8081, the container's own listeners, and `https://127.0.0.1`, a default the check refuses; `https://apt.postgresql.org/pub/repos/apt`, the public repository of the definition; `https://$host`, a template with a synthetic host.

## 14. The stop `CHECKPOINT_LIMIT` and D-113

The five checkpoint runs were used, and the fixes of R-01, R-02, R-04, R-06, R-15 and R-16 change what only a run of the job `Image checks` can prove (no `jq` here, no container here, D-108). The Task stopped at 08:47 with them uncommitted on `022aa90`, and kept them. The Owner answered with D-113: no further checkpoint run; those fixes reach the pull request as they are, and CI on its final head proves them.

What that run must show: the job `Command contract` green; the job `Image checks` green, with `Scan gate tests: 49 passed, 0 failed`, 268 checks passed and 0 failed, the file system arriving whole for the secret scan, `SCAN_GATE_UNUSABLE` absent from the real gate's result, and the three setup files absent from the image. A red run enters the fix cycles of CE-42, at most three, with every result kept here; a fix of a MUST-FIX red there has one more targeted check (CE-40). A red check is not a merge: `ci_result` of `scripts/executor/lib.sh` counts every check run of the head.
