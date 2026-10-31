# Toolchain

The capabilities this project needs, the tool chosen for each, and its real status. The requirement is the capability; a named tool may be replaced by an equivalent that is already present and adequate.

TASK-001 pins the toolchain of the empty application (section 1.1); a tool that a later Task adds stays `PLANNED` until that Task installs it. A status is changed to `VERIFIED` only by the Task that installs the tool and proves it works, recording version, scope and the verification performed.

## 1. Capabilities

| Capability | Tool | Requirement | Status | Notes |
| --- | --- | --- | --- | --- |
| Current documentation for version-sensitive work | Context7, or the official documentation of the exact version | REQUIRED before relying on any framework or library API | Context7 NOT VERIFIED in the executor environment; TASK-001 used the official documentation and registries (section 1.1) | Do not rely on remembered APIs for Laravel, Inertia, React, TypeScript, Tailwind, shadcn/ui or PostgreSQL. |
| Codebase impact analysis | Graphify, or equivalent targeted search | DEFERRED until application code exists, then required for significant changes | NOT INSTALLED | A map, not an authority. |
| Browser end-to-end testing | Playwright, as a project dev dependency | REQUIRED for every interface Task | VERIFIED by TASK-001 (section 1.1) | Use the project package and CLI, not an MCP server, unless interactive use materially helps. |
| Interface components | shadcn/ui with Tailwind CSS | REQUIRED; use before creating new primitives | VERIFIED by TASK-005: the design system's components in `resources/js/components/ui/`, written in shadcn/ui's pattern on its Radix UI primitives (section 1.1); Tailwind CSS VERIFIED by TASK-001 | The shadcn CLI only copies component source; TASK-005 did not need it (TASK-005 evidence, the readings). Pages compose these components. |
| Automated accessibility checks | axe-core through `@axe-core/playwright`, a dev dependency | REQUIRED for every interface Task (TEST_STRATEGY TS-25) | VERIFIED by TASK-005 (section 1.1) | Run in the Playwright tests at the widths of KEY_SCREENS section 4 and 1440 px; the harness is `tests/Browser/support/`. |
| Visual and frontend reference | Golden Frontend Reference v1.0.0, commit `14497d9` | REQUIRED for P7 design work (RF-4C) and later frontend work: the one approved reference (D-085) | APPROVED by the Owner (D-085); read only, nothing installed; tag `v1.0.0` confirmed at commit `14497d9` on 17 October 2026 | See section 3. AICWDF-4.3 requires a visual reference and prefers DesainPakaiAI; the requirement is the capability. DesainPakeAI is no longer required (section 5). |
| PHP quality | Pint, PHPStan with Larastan, Pest or PHPUnit | REQUIRED from the foundation Task | VERIFIED by TASK-001: Pint, PHPStan with Larastan, PHPUnit (section 1.1) | Real PostgreSQL is required for constraint, rollback and race tests; SQLite and mocks are not evidence for these. |
| Frontend quality | Lint, TypeScript typecheck, Vitest where behaviour warrants it, Vite production build | REQUIRED from the foundation Task | VERIFIED by TASK-001 (section 1.1) | |
| Version control | Git on the Owner's Windows machine | REQUIRED | In use | Identity and safety rules in [PROJECT_RULES](PROJECT_RULES.md) section 9. |
| Continuous integration | GitHub Actions on the public repository | REQUIRED from the foundation Task ([TEST_STRATEGY](../08-testing/TEST_STRATEGY.md) section 7) | VERIFIED — TASK-001 (`.github/workflows/ci.yml`): green on pull request #1 (run 39250580770) and on its merge into `develop` (run 39268427587), D-098 | Free on standard runners for a public repository (verified 18 October 2026); no environment secret in CI ([ADR-011](../adr/ADR-011-release-pipeline-environments-and-production-operation.md) item 4) |
| Release image registry | GitHub Container Registry, public package | REQUIRED for releases (ADR-011 item 5) | PLANNED | Images run by digest, never `latest` |
| Image scans (RELEASE_PROCESS section 4 item 3) | Gitleaks for the image's file system; Grype, with its Syft, for its packages | REQUIRED for the image checks of TASK-012 and the release of TASK-014 | VERIFIED by TASK-012 in CI (`scripts/checks/image-checks.sh`; section 1.1) | Each runs from its image, pinned by digest; Grype downloads its open vulnerability database at each run, with no account |
| Backup encryption and transfer | age; the PostgreSQL client tools of the server's major version; an S3 client of the framework's storage layer or the AWS SDK for PHP | REQUIRED for the backup container ([ADR-010](../adr/ADR-010-independent-backup-and-recovery-key-custody.md)) | age and the client tools VERIFIED in the image by TASK-012 (section 1.1); the S3 client PLANNED for TASK-013 | Licences recorded by the operations Task; every program started through the process API with an argument list (THR-10) |
| External heartbeat monitor | A free plan of a heartbeat service; candidate Healthchecks.io | REQUIRED before the pilot (ADR-011 item 11) | PLANNED | Free-tier record in [ENVIRONMENTS](../09-operations/ENVIRONMENTS.md) section 15; the operator holds the account |

Versions are pinned, with lockfiles, by the foundation Task after checking support windows, advisories and compatibility. Production never uses a floating `latest` tag.

### 1.1 Pinned versions

Pinned by TASK-001 (STK-01) in `composer.json` and `composer.lock`, `package.json` and `package-lock.json`, `compose.yaml` and `docker/app/Dockerfile`; every image by tag and digest. Checked on 19 October 2026 (Asia/Jakarta) against the official documentation and registries; the sources, the compatibility checks and the readings are in the [TASK-001 evidence](../11-tasks/evidence/TASK-001.md) section 4. `PINNED` means the version is checked; `VERIFIED` means the tool also ran in the command contract, locally, on 19 October 2026 (evidence section 10); none has run in CI yet.

| Component | Version | Licence | Support window | Advisory check | Status |
| --- | --- | --- | --- | --- | --- |
| PHP | 8.5.12, image `php:8.5.12-fpm-trixie` (digest `sha256:da5d8e27ec238adc0804fd4194f98e59477db7116690d275b76679c1b84c813f`), updated from 8.5.11 by TASK-012 because the scan of the image reports security fixes in 8.5.12; `composer.json` keeps its `require` and its `config.platform` of 8.5.11, whose change would change `composer.lock`'s content hash, and no package's constraint needs more | PHP License | Active support to 31 December 2027, security fixes to 31 December 2029 | Latest 8.5 release (image of 28 October 2026) | VERIFIED; by TASK-012 for 8.5.12 in CI |
| Composer | 2.10.3, image `composer:2.10.3` | MIT | Latest stable release | No advisory affects 2.10.3 | VERIFIED |
| Laravel | `laravel/framework` 13.37.0 | MIT | Bug fixes to Q3 2027, security fixes to 17 March 2028 | `composer audit`: none | VERIFIED |
| Laravel Fortify | `laravel/fortify` 1.41.0, installed by TASK-006 with the 20 packages it requires, none of them required by the application itself: `bacon/bacon-qr-code` 3.1.1, `dasprid/enum` 1.0.7, `doctrine/deprecations` 1.1.6, `laravel/passkeys` 0.2.1, `paragonie/constant_time_encoding` 3.1.3, `phpdocumentor/reflection-common` 2.2.1, `phpdocumentor/reflection-docblock` 6.0.3, `phpdocumentor/type-resolver` 2.1.0, `phpstan/phpdoc-parser` 2.3.6, `pragmarx/google2fa` 9.1.0, `spomky-labs/cbor-php` 3.4.2, `spomky-labs/pki-framework` 1.6.3, `symfony/polyfill-php81` 1.38.1, `symfony/property-access` 8.1.4, `symfony/property-info` 8.1.8, `symfony/serializer` 8.1.9, `symfony/type-info` 8.1.8, `web-auth/cose-lib` 4.8.2, `web-auth/webauthn-lib` 5.3.9, `webmozart/assert` 2.5.0; no installed package changed version | MIT; of the packages it requires, `bacon/bacon-qr-code` and `dasprid/enum` BSD-2-Clause, the others MIT | Latest major (Laravel: only the latest major of a library receives bug fixes) | `composer audit`: none | VERIFIED by TASK-006: only the feature of the own password change is enabled, Fortify's routes are not registered, and the application registers RA-01 to RA-05 on Fortify's controllers; the two-factor and passkey packages are installed because Fortify requires them, and no route or feature uses them ([TASK-006 evidence](../11-tasks/evidence/TASK-006.md)) |
| Inertia, Laravel adapter | `inertiajs/inertia-laravel` 3.5.1 | MIT | None published; latest release | `composer audit`: none | VERIFIED |
| Inertia, React adapter | `@inertiajs/react` 3.9.1 | MIT | None published; latest release | `npm audit`: none | VERIFIED |
| React | `react`, `react-dom` 19.3.1 | MIT | None published; latest release | `npm audit`: none | VERIFIED |
| TypeScript | 6.0.3 | Apache-2.0 | None published; latest 6.0 release (typescript-eslint supports `<6.1.0`) | `npm audit`: none | VERIFIED |
| Vite | 8.3.4, with `@vitejs/plugin-react` 6.1.2 and `laravel-vite-plugin` 3.2.0 | MIT | Vite 8.3 receives regular patches | `npm audit`: none | VERIFIED |
| Tailwind CSS | `tailwindcss`, `@tailwindcss/vite` 4.3.3 | MIT | None published; latest release | `npm audit`: none | VERIFIED |
| shadcn/ui tooling | `shadcn` CLI 4.22.0, selected by TASK-001; not installed | MIT | Latest release (4.22.1 on 24 October 2026) | GHSA-vfj7-8cjw-p6xm on `braces`, withdrawn by GitHub on 23 October 2026 | NOT INSTALLED: TASK-005 wrote the components in the shadcn/ui pattern without the CLI, which only copies source |
| Radix UI primitives of shadcn/ui | `@radix-ui/react-dialog` 1.3.1, `@radix-ui/react-dropdown-menu` 2.2.1, `@radix-ui/react-radio-group` 1.5.1, `@radix-ui/react-toggle` 1.2.1, runtime; with `get-nonce` 1.0.1, which gives the CSP nonce to the scroll lock of `react-remove-scroll` | MIT | None published; latest releases (14 October 2026) | `npm audit`: none | VERIFIED by TASK-005 |
| Accessibility checker | `@axe-core/playwright` 4.14.0 with `axe-core` 4.14.0, dev | MPL-2.0 | None published; latest release | `npm audit`: none | VERIFIED by TASK-005 |
| Inter font | `@fontsource-variable/inter` 5.3.0, dev: the source of the self-hosted files in `public/fonts/inter/` (Latin and Latin Extended, opsz and wght axes), with their licence `OFL.txt` | OFL-1.1 (the font files) | None published | `npm audit`: none | VERIFIED by TASK-005 |
| Brick Math | `brick/math` 1.0.0 | MIT | None published; latest release | `composer audit`: none | PINNED; installed, first used by TASK-003 |
| Image library (SEC-UP-03) | GD, PHP's bundled `gd` extension of PHP 8.5.11 (libgd as bundled with PHP), built in `docker/app/Dockerfile` with `docker-php-ext-configure gd --with-jpeg --with-webp` on the base image's Debian trixie libraries `libpng`, `libjpeg62-turbo` and `libwebp`; chosen by TASK-010 for the media pipeline (`App\Domain\Media\ImageNormalizer`) | GD: the PHP License (bundled libgd: the GD licence, BSD-style); libpng: the libpng licence; libjpeg-turbo: IJG and BSD-3-Clause; libwebp: BSD-3-Clause | Follows PHP 8.5 and the base image's Debian packages | Updated with the PHP image and Debian trixie's security archive | VERIFIED by TASK-010 in CI. GD has no external delegates: it never starts another program or reads anything but the bytes it is given, so none has to be disabled (the stop condition of TASK-010 does not apply); decoding runs only after `ImageInspector` passed the header ([TASK-010 evidence](../11-tasks/evidence/TASK-010.md)) |
| PHPUnit (one of Pest or PHPUnit) | `phpunit/phpunit` 13.4.4, with `sebastian/git-state` 1.1.0, which 13.4.4 requires (`^1.1`); updated from 13.4.3 and 1.0.0 by TASK-007 (D-106), no other package changed | BSD-3-Clause | Bug fixes to 4 February 2028 | `composer audit`: none. 13.4.4 is the first release patched against GHSA-9vw2-f4vp-x7gf (Packagist `PKSA-qhjm-rpqc-qkn6`, `>=13.4.0,<13.4.4`, 27 October 2026), the shared temporary directory of PHPT code coverage | VERIFIED; by TASK-007 for 13.4.4 ([TASK-007 evidence](../11-tasks/evidence/TASK-007.md) section 13) |
| PHPStan with Larastan | `phpstan/phpstan` 2.3.1, `larastan/larastan` 3.13.0 | MIT | None published; latest releases | `composer audit`: none | VERIFIED |
| Pint | `laravel/pint` 1.32.1 | MIT | Latest major | `composer audit`: none | VERIFIED |
| ESLint | `eslint` 10.13.0, `@eslint/js` 10.0.1, `typescript-eslint` 8.71.2, `eslint-plugin-react` 7.37.5, `globals` 17.13.0 | MIT | ESLint 10 is the current line; ESLint 9 ended on 6 August 2026 | `npm audit`: none | VERIFIED |
| Vitest | 5.0.5 | MIT | None published; latest release | `npm audit`: none | VERIFIED |
| Playwright | `@playwright/test` 1.64.1, image `mcr.microsoft.com/playwright:v1.64.1-noble` | Apache-2.0 | None published; latest release | `npm audit`: none | VERIFIED |
| Node.js (LTS) | 24.22.0, image `node:24.22.0-trixie-slim`; `@types/node` 24.19.2 | MIT | LTS "Krypton": maintenance from 20 October 2026, end of life 30 April 2028 | Latest 24.x release | VERIFIED |
| PostgreSQL | 18.6, image `postgres:18.6-trixie` (digest `sha256:74935e72241653ca55e0414067e6d8763aceb8a810eb51b452253ec3dcfc4336`), for the server and, in the same image, for `psql`, which runs the setup script `database/setup/setup.sql`; the setup refuses another major version | PostgreSQL License | Major 18 supported to 14 November 2030 | Current minor; no fix listed after 18.6 | VERIFIED; the features that SCHEMA relies on proven on it by TASK-004 (SCHEMA section 15, [TASK-004 evidence](../11-tasks/evidence/TASK-004.md) section 6): UTF-8, libc provider, locale `en_US.utf8` of glibc 2.41, `pg_trgm` 1.6 |
| Nginx | 1.30.6-1~trixie, from nginx.org | BSD-2-Clause | Stable branch 1.30 | Not affected by any advisory that nginx.org lists | VERIFIED |
| Grype (vulnerability scan of the image), with Syft | Grype 0.121.0, with Syft 1.55.0 inside it, image `anchore/grype:v0.121.0` (digest `sha256:621d56d1d68106b787022c81546f7e0a36202a25d1239f0c20195fab8c16f7ea`), named in `scripts/checks/image-checks.sh` | Apache-2.0 | Latest release (19 October 2026) | Its database is downloaded at each run | VERIFIED by TASK-012 in CI |
| age (backup encryption) | 1.3.2, built with `go install -trimpath` in the `age` stage of `docker/app/Dockerfile` on the image `golang:1.27.3-trixie` (digest `sha256:c65ecfe613b539ac74c62a4c3b2c27e8f8a6076384c8f27e8a3c0f3be17cc385`), because Debian's package is built with Go 1.24.4, for which the scan reports fixed vulnerabilities; the program is copied into the image, Go is not | BSD-3-Clause; Go: BSD-3-Clause | Latest release (29 August 2026) | Sources verified by Go's checksum database; scanned with the image | VERIFIED by TASK-012 in CI; `backup:run` of TASK-013 starts it at `/usr/local/bin/age` |
| PostgreSQL client tools | `postgresql-client-18` of the PostgreSQL Apt Repository for Debian trixie, pinned by the server's major version, its signing key checked by fingerprint | PostgreSQL License | Follows major 18 | Scanned with the image | VERIFIED by TASK-012 in CI: `pg_dump` of the image reaches the pinned server; `backup:run` of TASK-013 starts `pg_dump` and `pg_restore` at `/usr/lib/postgresql/18/bin/`, not through Debian's Perl wrapper |
| The backup store's S3 client and the heartbeat's ping client | No package: `App\Domain\Operations\Backup\ObjectStore` and `SigV4` (TASK-013), signed requests over Guzzle 8.3.0, which the framework already requires, with its `StreamHandler` set explicitly (`App\Domain\Operations\OutboundHttp`), PHP's HTTP wrapper over OpenSSL | The application's own code; Guzzle: MIT | — | Reaches neither libcurl nor libxml2 (OPEN_ITEMS T-38, TASK-013 evidence) | VERIFIED by TASK-013 in CI against the test server; the real store by the operator at gate G4 (TASK-045) |
| S3-compatible test server | MinIO `RELEASE.2025-04-22T22-12-26Z`, built from its source by `docker/backup-store/Dockerfile` on the image `golang:1.27.3-trixie` with the toolchain go1.24.2, because the repository `minio/minio` on Docker Hub no longer exists (TASK-013 evidence, section 8); the service `backup-store` of `compose.yaml`, for the tests only; never in the release image and never on a path of staging or production | AGPL-3.0 (used unmodified as a separate test service; nothing of it is distributed) | A 2025 release; it supports Object Lock, versioning and multipart uploads, which is all the tests need | Sources and toolchain verified by Go's checksum database; holds only what the tests write, on CI's internal network | VERIFIED by TASK-013 in CI |
| Gitleaks (secret scan) | 8.30.1, image `ghcr.io/gitleaks/gitleaks:v8.30.1`; on the Owner's machine the release's Windows x64 binary of the same version, in `%LOCALAPPDATA%/BantuKasir/tools/gitleaks/8.30.1/`: archive `gitleaks_8.30.1_windows_x64.zip`, SHA-256 `d29144deff3a68aa93ced33dddf84b7fdc26070add4aa0f4513094c8332afc4e`, equal to the release's checksums file and to GitHub's asset digest; `gitleaks.exe`, SHA-256 `17157e2ee8b76fc8b1d8bee607a250e34b8a8023c8bc81822d4b5ee4d78fcb7c`, which the publication guard requires natively (EXECUTION_CONTEXT CE-31, D-108) | MIT | Latest release | No advisory affects 8.30.1 | VERIFIED in the image; the native binary VERIFIED on 29 October 2026 (checksums above, `gitleaks version` 8.30.1); the release publishes no signature or build attestation |

## 2. Installing tools

An agent may install a missing development tool on its own only when the active Task needs it and the installation is reversible, project-local or a dev dependency where possible, exposes no secret, triggers no unrelated major upgrade and touches nothing in production. After installing, verify the tool and record tool, purpose, version, scope, verification and files changed.

Stop and ask the Owner when a tool needs a login, an API key, a subscription or payment, administrator rights, an external account, or a risky infrastructure change. Complete every safe step first, then state the minimum action needed.

No tool is installed during the re-foundation steps.

## 3. Frontend reference

The approved reusable frontend reference is the **Golden Frontend Reference v1.0.0** — Golden Frontend Reference v1.0, public release `v1.0.0`, baseline `golden-frontend-v1.0` — which the Owner established on 17 October 2026 (D-085). It is the visual reference for P7 design work (RF-4C) and for later frontend work. No design tool is required, and a design-generation tool or service is used only with the Owner's authorization. The reference authorizes no work: it is used only inside a step that the Owner authorizes, and it approves no BantuKasir artifact; gate P7-G3 is a separate Owner decision.

- **Identity.**
  - Repository `https://github.com/yusufarst/golden-frontend-reference`, public, on the Owner's account. Commit `14497d997ee7994dfa9153d41b8cea636578e24c`, tree `3f19c1e253fd5c21eeb2483e0aa513e0ef1861ee`, at which the annotated tag `v1.0.0` (object `9d46af300949b2dd4b2f3d443652d78c38067deb`) points.
  - Frozen scope: the 41 files listed in `release/golden-frontend-v1.0/SHA256SUMS-scope.txt`, list digest `39fa879dc304275e55b1ffecda290ebd6b481a27d89bbda67011c26e0796eca4`.
  - Before relying on it, confirm that the tag still resolves to that commit (`git ls-remote` shows `refs/tags/v1.0.0^{}` at it), and read from the commit, never from a branch. On a mismatch, stop.
  - Another version (v1.1, v2.0 or any other) or another commit needs the Owner's decision. The replacement rule in the introduction of this document does not apply to this reference.
  - It is read only: never modify it.
- **What may be adapted.** Its generic layer, translated into the approved stack — Laravel, Inertia and React with TypeScript, Tailwind CSS and shadcn/ui (D-004, ADR-001, [STACK_AND_MODULES](../04-architecture/STACK_AND_MODULES.md)) — without a framework migration:
  - design tokens: the names, values and semantic roles of colour, typography, spacing, radius, elevation, surfaces, status tones, density and motion;
  - components: anatomy, states, sizes, and keyboard, focus and accessibility behaviour, built as the stack's own components, with shadcn/ui before new primitives (section 1);
  - UX patterns and principles: page archetypes, numbered lists and pagination, validation and recovery, confirmation levels, result anatomy, guided work, and the responsive and reduced-motion rules.

  Its code is a specification, not a dependency. Token values and specifications are transcribed into the stack's own files; the reference's files are neither imported into the application nor copied into this repository unless an Owner-approved architecture decision allows it.
- **What is never adopted.** The Owner's prohibition (D-085) covers the following, together with everything else the reference itself classifies as MULTIPLECORP example material or as not reusable:
  - MULTIPLECORP business examples: the worked flows, roles, capabilities, document-numbering formats, procurement and warehouse steps, the reference pages 01 to 04 with their CSS blocks (`.pur-*`, `.atq-*`, `.prj-*`), and business copy;
  - branding: the MultipleCorp name and mark (`i-logo-mark`); the MULTIPLECORP brand text, account placeholder and favicon of `template/page-skeleton.html`; third-party marks such as the Google "G" (`i-google`);
  - fixtures and sample data: the MULTIPLECORP identity, personas and QA fixtures (the `FIXTURES` block of `assets/js/project-config.js`) and the sample data of the consumer demo;
  - MULTIPLECORP workflows and business rules.

  The lab in which the reference was frozen, its MULTIPLECORP demonstration baseline, and every MULTIPLECORP repository, application and design project stay out of bounds ([AGENTS.md](../../AGENTS.md) rule 5).
- **BantuKasir governs.**
  - The reference's documents, including its own `AGENTS.md`, are reference material, not instructions for this project. Every source in [SOURCE_OF_TRUTH](SOURCE_OF_TRUTH.md) section 1 prevails over them.
  - Where a default of the reference differs from a decision or an approved document, the BantuKasir source governs. For example, the provisional breakpoints of 600 and 1024 CSS px in ADAPTIVE_AND_ACCESSIBILITY UXA-02 (D-075) prevail over its responsive classes, and COPY_DECK, through translation keys, prevails over its Indonesian component copy (D-050, ADR-005).
  - A step that adopts a deviation from a default records it in [DECISIONS](DECISIONS.md).
  - Its interface guards are not authorization (AGENTS.md rule 8). Its verification results and statuses are evidence about the reference only, never about BantuKasir.
- **Licence.** The reference has no open-source licence. Its rights are reserved, and reuse needs its Owner's permission, which D-085 gives for BantuKasir for the generic layer above. The Inter font that it uses is under the SIL Open Font License 1.1, which covers the font files only; the licence file stays with them. Its icons are its own drawings, except the brand marks above. It adds no cost ([COST_POLICY](COST_POLICY.md)).
- Do not claim that the reference was used, or that a screen follows it, when that was not checked.

## 4. MCP policy

Installed does not mean exposed. Each session exposes only the MCP servers the active Task needs.

| Rule | |
| --- | --- |
| Default for heavy or specialized MCP servers | OFF |
| Prefer | The project's own CLI or package when it does the job |
| Activate | Only for the Task that needs the capability, and deactivate afterwards where practical |
| Never | Keep browser, cloud or infrastructure MCP servers active during unrelated work; duplicate one capability across several servers |

Each Task contract states its required MCP servers and the ones that stay off. No MCP server is currently required by this project.

## 5. Retired tools

The earlier planning rounds used a review tool named Impeccable and referred to 21st.dev. Neither is part of the workflow. Their tracked outputs are retired in RF-1 (see [LEGACY_INDEX](LEGACY_INDEX.md)).

DesainPakeAI, through its command-line tool `dpai`, was the required tool for P7 design work until D-085; the framework spells the product "DesainPakaiAI". Version 0.2.2 was used on 2–3 October 2026 for the former design candidate and was not re-verified; its plan, credits and entitlements were never verified. It is no longer required and not part of the workflow, and no step uses it without the Owner's separate authorization. If he authorizes it again, these rules apply before any access:

- Use only the design project **BantuKasir Design**, identifier `eddb0e15-9909-44b3-8517-9239cadc65ef`, which also holds eight unrelated CRM template pages (OPEN_ITEMS T-08). Before reading context and before every mutation, run `dpai project current --pretty` and confirm that exact name and identifier. On any mismatch, stop before further access.
- Never select, read, export or mutate a MULTIPLECORP design project, and never use its assets, tokens or layouts.
- Do not use the DesainPakeAI MCP server. Do not repeat login or setup inside a Task. Never store an access key in the repository, in evidence or in a screenshot.
- Design mutations and anything that may consume paid credit need the Owner's authorization for that scope.
- The tool may propose layout, hierarchy, composition, interaction patterns and design-system choices; it may not redefine business rules. Its prototype output is never shipped as the application. Do not claim it was used when it was not.

Its evidence from the former candidate stays in `docs/07-ux-design/legacy/` as RECORD.
