# TASK-012 — Evidence

| | |
| --- | --- |
| Task | [TASK-012](../tasks/TASK-012-application-image.md), Application image and runtime configuration; review class B |
| Authorization | Continuous execution, D-101 as amended by D-104, D-105, D-107, D-108 and D-109 (EXECUTION_CONTEXT CE-01) |
| Executor | `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19 |
| Base | `d2ec7a67d021b2f4db09beb5ba9605bd6e6f7297`, the squash merge of TASK-011 |
| Branch | `task/TASK-012-application-image` |

The record is completed when the Task closes (EXECUTION_CONTEXT CE-50).

## Stop for the Owner: findings of the vulnerability scan

Contract section 10 stops the Task on "a high or critical finding without an accepted reason". Accepting a risk is the Owner's (AGENTS rule 3).

Run 41129923061 on `ef64c1a`, Grype 0.121.0 with its database of 30 October 2026: 112 lines of high or critical, 43 distinct CVEs (7 critical, 36 high), every one a Debian package with the fix state `wont-fix` or `not-fixed`; none has a fix to install.

| Source package | Findings | CVEs |
| --- | --- | --- |
| curl, libcurl4t64 | Critical | CVE-2026-10536, -11856, -18924, -19931, -8926, -9079 |
| curl, libcurl4t64 | High | CVE-2026-12064, -13608, -80229, -80230, -80255, -82209, -86409, -86410, -8932, -9080, -92386, -92387, -9545 |
| libxml2 | Critical | CVE-2026-6653 |
| libxml2 | High | CVE-2026-74860, -86138, -86139, -86140, -86142, -86143, -86144 |
| util-linux and its libraries | High | CVE-2026-76642, -78408, -78409, -78410 |
| glibc | High | CVE-2026-19499, -5435 |
| gcc runtime | High | CVE-2026-102010, -95619 |
| perl | High | CVE-2026-82560, -9538 |
| acl | High | CVE-2026-54369, -54370 |
| krb5 | High | CVE-2026-107778 |
| cyrus-sasl | High | CVE-2026-107161 |
| ncurses | High | CVE-2025-69720 |
| zlib | High | CVE-2026-85091 |

The fixable findings of the first run are gone: PHP 8.5.11 → 8.5.12, and age from source with the current Go in place of Debian's build with Go 1.24.4. These libraries belong to the base image or to PHP's own build (libcurl, libxml2), so they cannot be removed from it.

## State when the Task stopped

| Check | Where it ran | Result |
| --- | --- | --- |
| Command contract | CI run 41129923061, head `ef64c1a` | PASS |
| Image checks | CI run 41129923061, head `ef64c1a` | 264 passed, 1 failed: the finding above |

After that run, committed and not pushed: the pool `limit-check` of `docker/app/php-fpm.conf` (a request ended after 78 seconds, past the client's wait of 65; the check now requires at most 64), which no run has measured yet; the records in TOOLCHAIN and ENVIRONMENTS; this section.

Three checkpoint runs of five are used. On resume: apply the Owner's answer in `scripts/checks/image-checks.sh` and record it as a decision; the fourth checkpoint; then the review of CE-40 and the record of CE-50.
