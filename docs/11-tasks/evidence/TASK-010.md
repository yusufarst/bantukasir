# TASK-010 evidence — Installation bootstrap, BusinessProfile and media

| | |
| --- | --- |
| Task | [TASK-010](../tasks/TASK-010-bootstrap-profile-media.md), size L, review class A, lane P |
| Status | `DONE` when its pull request is squash-merged and CI passes on `develop` at the merge (EXECUTION_CONTEXT CE-50, CE-61); until then `develop` shows `READY`. The status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | D-101 as amended by D-104, D-105, D-107, D-108 and D-109, continuous execution (EXECUTION_CONTEXT section 19, CE-01): the selection of CE-10 at `origin/develop` `a6cd66d`, the lowest-numbered `READY` Task with its dependencies `DONE` and its gate GATE-CC1 satisfied; mode `start`, delegated by the tick to `bk-executor-high` on 30 October 2026 at 00:11:29, after it verified the merge of TASK-009; stopped by the Owner (`OWNER_STOP`) at 00:51:34 in `BUILD`, at stage 4 (RC-32), with the work kept uncommitted; resumed in `BUILD` on 30 October 2026 at 08:56:15 (resume 1 of 3) under D-109, on the Owner's note naming the merge of pull request #16 and the move of the branch to `befdd2e` |
| Branch | `task/TASK-010-bootstrap-profile-media`, created from `origin/develop` (CE-02) |
| Base commit | `a6cd66d609e7f928f46f31ae6b02a6be9d3260d9`, the squash commit of pull request #15 (TASK-009) on `develop`; since 30 October 2026 `befdd2e8812bb7b1621ce7eb706bc7beb647094b`, the squash commit of the `ops/` pull request #16 (D-109), to which the Owner moved the branch by fast-forward before any commit of the Task. The 9 files of D-109 are infrastructure, outside the Task's scope; the review packages use the base `befdd2e` |
| Executor | The implementation executor agent `bk-executor-high` in Claude Code Desktop on the Owner's Windows machine, in the main working tree; Claude Opus 5.5 (`claude-opus-5-5`); effort High, from the table of EXECUTION_CONTEXT section 19 (CE-11, D-107) |
| Reviewers | `bk-reviewer-high` for the adversarial review of class A and for a verification of fixes (CE-11, CE-40); section 8 |
| Gates | GATE-CC1, SATISFIED (D-099); EXECUTOR, checked at the start |
| Authorized beyond local commits | By CE-01 and D-105, for this Task only: its branch, its commits and pushes through the scripts without a prompt (CE-30), one pull request into `develop` and its CI, the fixes of CE-40 to CE-43, and the tick's squash merge where CE-60 allows it |

Times are Asia/Jakarta. Statuses use the vocabulary of [TEST_STRATEGY](../../08-testing/TEST_STRATEGY.md) section 8 (`NOT_RUN`, `PASS`, `FAIL`, `BLOCKED`).

## 1. Start and resume of the Task (EXC-20 to EXC-24, CE-20, CE-24)

- **Start (30 October 2026, 00:11).** The tick delegated the Task in mode `start` after `MERGE_VERIFIED pr=15`; the first session created the branch from `a6cd66d` and built, uncommitted, the migration and grants of the five tables, the Media pipeline (`app/Domain/Media/`), the Configuration services (`app/Domain/Configuration/`), the onboarding state (`app/Domain/Inventory/OnboardingState.php`), the bootstrap Action and the operator-procedure framework (`app/Actions/Access/Bootstrap*`, `app/Console/Commands/`), the Action of RC-32 and the hook `CommandAction::beforeTransaction()`. The Owner stopped it at 00:51:34 during stage 4 (RC-32); the work stayed in the working tree; no evidence file had been written yet.
- **Resume (30 October 2026, 08:57, this session).** Read path (EXC-01): AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT section 19, the contract and the parts of its "Read first" that the remaining scope needs — INTERACTION_CONTRACTS section 1.1, RC-32 and section 3.7; ROUTE_CONTRACTS RT-12, RP-601, RP-607, RP-608, RD-07 and RC-32; SECURITY_MODEL SEC-CH-02, SEC-CH-06, section 13 and section 14; DESIGN_SYSTEM sections 3.3 and 7.2; COPY_DECK sections 11.1, 11.7, 11.8 and 16.2; INTERACTION_INVENTORY UXI-008 and UXP-601, UXP-607, UXP-608; NAVIGATION_CONTRACTS UXP-601, UXP-607, UXP-608; UI_STATES UXP-601, UXP-607, UXP-608; REQUIREMENTS REQ-SCOPE-03; STACK_AND_MODULES section 2.1.
- **Model (CE-13).** `claude-opus-5-5`, the model that CE-11 names; no stop.
- **State (CE-24).** Phase `BUILD`, Task TASK-010, class A, branch `task/TASK-010-bootstrap-profile-media`, base `a6cd66d` in the state, effort `high`, agents `bk-executor-high`, `bk-reviewer-high`, `bk-reviewer-high`; the lock held by this session's token; `task.resumes=1`.
- **Working tree.** The branch at `befdd2e`, no commit of the Task; 3 tracked files modified and 26 untracked files of the Task, every one accounted for by the contract and the state log above; besides them only `Claude outputs/` and `plan-p0-p11-review.patch`, which CURRENT_HANDOFF section 2 and the Owner's note account for and which this Task never touches. Nothing was reset, cleaned, restored or stashed.
- **The stops of the Task and their resumes.** `CHECKPOINT_LIMIT` after the fifth, seventh and eighth checkpoint runs (D-110; section 9), and `REVIEW_MUST_FIX_OPEN` at 17:38 (section 8), which the Owner answered at 19:47:11 with his option b. This session resumed in the phase `REVIEW` at 19:47:36 (resume 1 of 3), with its token, the working tree as that stop left it: the fixes of R-05, R-18 and R-27 and the records uncommitted, every file accounted for by sections 8 and 9.
- **Tools (D-109, CE-47).** Files were read with Read, Grep and Glob and changed only with Edit and Write; every Bash call was one allowed command. The native Node toolchain has no command links in `node_modules/.bin`, so `npm run typecheck` could not start (`tsc` not found) and `npx tsc` or `npx eslint` would fetch a package; they were not run locally, and their run of record is CI (section 9). PHP, PostgreSQL, the race suite and the browser suite run in checkpoint runs of CI only (D-108).
- **The previous merges (CE-50).** Pull request #15 (TASK-009) is recorded in the [TASK-009 evidence](TASK-009.md) section 15, and the `ops/` pull request #16 (D-109) in [CONTINUOUS_EXECUTION_SETUP](../../00-governance/records/CONTINUOUS_EXECUTION_SETUP.md) section 13.4, both in CURRENT_HANDOFF.

## 2. Versions and sources checked (EXC-03)

| Fact relied on | Pinned version | Source and result |
| --- | --- | --- |
| GD decodes PNG, JPEG and WebP from a string and encodes them again without metadata; `imagerotate` turns counter-clockwise; it never starts another program | PHP 8.5.11 `gd` (bundled libgd) | The PHP manual of `imagecreatefromstring`, `imagerotate`, `imagepng`, `imagejpeg`, `imagewebp`, `imagetypes`; proven by `ChangeBusinessProfileTest` (orientation 6 turned upright, no EXIF and no comment left) |
| `getimagesizefromstring` reads the dimensions from the header without decoding, also of an extended WebP (`VP8X`) | PHP 8.5.11 | `ext/standard/image.c` (`php_handle_png`, `php_handle_webp`); proven by the refusal of a header that claims 20 megapixels and of an animated WebP |
| A partial reload of Inertia leaves out every prop that it does not name, shared ones included, unless it is an `always` prop | inertiajs/inertia-laravel 3.5.1 | `SharedPropsTest` (the background reload's prop names, unchanged by this Task) |
| A named rate limiter that returns `Limit::none()` lets the request through uncounted | Laravel 13.37.0 | `ThrottleRequests::handleRequestUsingNamedLimiter` (`Unlimited`) |
| `client_max_body_size` of the location that serves the request; `upload_max_filesize` and `post_max_size` | Nginx 1.30.6, PHP 8.5.11 | The Nginx and PHP documentation |

The image library added is GD, PHP's bundled extension, recorded in [TOOLCHAIN](../../00-governance/TOOLCHAIN.md) section 1.1 with its licences (the PHP License; libpng's licence; libjpeg-turbo's IJG and BSD-3-Clause; libwebp's BSD-3-Clause). No Composer or npm package was added.

## 3. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. The five tables with their checks, keys, the deferred presence key `release_presence` and their grants | `database/migrations/2026_10_30_000001_create_profile_media_onboarding_tables.php`; `database/privileges.php` |
| 2. The media pipeline: content judged by its signature and header, dimensions before decoding, animation refused, size limits, orientation applied, re-encoding without metadata by GD, random keys of 256 bits, private storage outside the web root (disk `media`), the file written before the transaction with an exclusive create, `sha256` recorded, nothing overwritten or deleted; RD-07 | `app/Domain/Media/`; `config/filesystems.php`; `app/Http/Controllers/Media/MediaController.php`; `docker/app/Dockerfile` (GD, upload limits); `docker/app/nginx.conf` (body limit) |
| 3. OP-01 `owner:bootstrap` and the operator-procedure framework | `app/Console/Commands/OperatorProcedure.php`, `OwnerBootstrap.php`, `ProcedureRefused.php`; `app/Actions/Access/BootstrapInstallation.php`, `BootstrapInput.php`, `BootstrapResult.php`; `app/Domain/Access/BootstrapRefused.php`; `app/Domain/Configuration/BusinessProfiles.php`; `app/Domain/Inventory/OnboardingState.php` |
| 4. RC-32 `business_profile.change`, JSON or multipart with the logo | `app/Actions/Configuration/ChangeBusinessProfile.php`; `app/Http/Requests/Configuration/ChangeBusinessProfileRequest.php`; `app/Http/Controllers/Configuration/ChangeBusinessProfileController.php`; `CommandActions::ACTIONS`; the client's `formOf()` in `resources/js/lib/commands/client.ts` |
| 5. UXP-601, UXP-607, UXP-608 | `resources/js/pages/access/Settings.tsx`, `resources/js/pages/configuration/Profile.tsx`, `ProfileEdit.tsx`, `resources/js/lib/configuration/profile.tsx`; `app/Http/Controllers/Access/SettingsController.php`, `app/Http/Controllers/Configuration/ProfileController.php`; `app/Queries/Configuration/ProfileRevisions.php`, `app/DTO/Configuration/RevisionData.php`; UXI-008 in `resources/js/lib/navigation.ts`; the keys of COPY_DECK sections 11.1, 11.7, 11.8 and 16.2 in `lang/id/config.php` and `lang/id/ux.php` |
| 6. The shop's name, initials and accent in the shell and the document title | `app/Queries/Configuration/Shop.php`, `app/DTO/Configuration/ShopData.php`, `HandleInertiaRequests` (`shop`); `AppShell.tsx` (brand, `documentTitle`); `app.tsx` and `lib/accent.ts` (`followPageAccent`) |
| 7. Client-neutral code | No client name, logo or picture in the code or assets (`ClientNeutralTest`, the browser test of the build) |

Routes added (ROUTE_CONTRACTS): RP-601 `GET /settings` (`settings.index`, any of CAP-25, CAP-30, CAP-31, CAP-32), RP-607 `GET /settings/business-profile` (`profile.show`, CAP-30), RP-608 `GET /settings/business-profile/edit` (`profile.edit`, CAP-30), RC-32 `POST /commands/business_profile.change` (CAP-30, buckets `command` and, with a logo, `logo`, recent authentication), RD-07 `GET /media/{file}` (`media.show`, CAP-01, `variant` allowed).

## 4. Readings and technical choices

1. **SEC-CH-02 (contract scope item 6, section 8).** The shop's name, its initials and its accent reach every signed-in page as the shared prop `shop`, by an amendment of SEC-CH-02 written into [SECURITY_MODEL](../../05-security/SECURITY_MODEL.md) section 7: non-sensitive configuration that every role sees in the shell, from the revision in force only, never before sign-in (the key is `null` for a visitor, and set on every answer so that no earlier value stays in the process), never another field of the BusinessProfile. A per-page prop would have put the same value into every page's own props. The prop is lazy and not `always`: a partial reload that does not name it keeps the page's value. The review is asked whether this changes a privacy rule (contract section 10).
2. **The image library.** GD, PHP's bundled extension, built with PNG, JPEG and WebP only. It has no external delegates (it never starts a program or reads a file by name), so the stop condition "cannot disable external delegates" does not apply. Without GD support for a type `ImageNormalizer` refuses to run (a server error), never accepts unprocessed bytes.
3. **Multipart RC-32.** The envelope travels as form fields: `version` and `expected[revisionId]` arrive as texts and are read as whole numbers only when they are canonical; an empty field is null (`ConvertEmptyStringsToNull`); the file's SHA-256 stands for it in the fingerprint (TASK-008's `Fingerprint`); the JSON body limit of RT-12 applies to JSON only. Without a new logo the client sends JSON.
4. **The logo's bucket.** RC-32 counts in the bucket `logo` only when it carries a logo: the limiter of `logo` lets a command request without a file through uncounted; every other route that names the bucket counts every request (`RateLimitsTest` unchanged).
5. **Body limits (RT-12, SEC-UP-02).** The application refuses a logo over 5 MiB (`max:5120` and the Action) and a JSON command over 1 MiB; the local and CI container sets Nginx's `client_max_body_size` to 6 MiB and PHP's `upload_max_filesize` 6 MiB and `post_max_size` 7 MiB, just above. The release configuration is TASK-012's, and TASK-018 raises the limits for photos (8 MiB).
6. **RD-07.** Its path names the stored file's internal key, never its storage key; a file not found, not a number, a photo for a role without CAP-03, any `variant` (TASK-018 adds the thumbnail) or a missing stored file answer NOT_FOUND. The route carries every signed-in middleware of RT-04 except `cache.headers:no_store;private`, so that its answer keeps `private, max-age=86400, immutable` (SecurityHeaders leaves a chosen policy).
7. **Settings index.** The server sends only the entries whose page exists and whose capability the user holds (`SettingsController::ENTRIES`); later Tasks add theirs. UXI-008 shows for CAP-25 or CAP-32, as its row of INTERACTION_INVENTORY says.
8. **The accent** is applied to the document from the page's shared props before React renders (`app.tsx`, on the first page and each Inertia navigation); a page without a shop — before sign-in — keeps the default green of a fresh document (DESIGN_SYSTEM section 3.3).
9. **The fixtures (EXC-45, contract section 12).** `TokoContoh::shop()` installs Toko Contoh through OP-01's Action on an empty database, Hendra its Owner; on a database where earlier fixtures already created accounts or the epoch, the same services write what is missing. The browser fixtures install it before their people.
10. **The bootstrap's audit category** is `SECURITY`, as TASK-006's `PASSWORD_CHANGE` and `LINK_CONSUME`: security-relevant changes in the business audit (SEC-LOG-02).
11. **Module dependencies (STACK_AND_MODULES section 2.1).** The bootstrap is an Action of the Access module (operator procedures) that writes through the Configuration, Inventory and Commands services, as the section allows an Action to do; no dependency of a domain module was added, and Configuration's dependency on Media is the one already listed, so the document is unchanged.
12. **A file stored before its transaction** stays unreferenced when the command is refused after it or rolled back, and when a repeat of a committed multipart request stores its file again before the identity answers with the original result; TASK-038's housekeeping removes unreferenced files after their grace period.

## 5. Findings

| ID | Finding | Outcome |
| --- | --- | --- |
| F-1 | COPY_DECK section 11.8 has no text for a control that removes the logo; RC-32 accepts `logoAction` `remove` | UXP-608 offers choosing or replacing the logo only; RC-32 and its tests cover `remove`. For the change control of TASK-047 (GATE-CC2) |
| F-2 | `composer.json` does not require `ext-gd`: adding it would change `composer.lock`'s content hash | The image installs GD (`docker/app/Dockerfile`); `ImageNormalizer` refuses to run without it. For TASK-012's release image |
| F-3 | The release image's body and upload limits (RT-12); and, by review finding R-06, the local and CI container's Nginx limit of 6 MiB applies to every route, where RT-12 asks for 1 MiB at the proxy for JSON and form bodies and 5 MiB for RC-32 only | A recorded deviation of the local and CI container, which the application's own limits cover (1 MiB of JSON in `CommandRequest`, 5 MiB for the logo); a per-route limit needs a location of its own with the front controller's parameters, which TASK-012's release configuration writes |
| F-4 | COPY_DECK has no message for a text with a control character (TR-VAL-05); only `ux.validation.sku` names one | RC-32 refuses such a text VALIDATION_FAILED with the summary of UXS-05 and no field message (review finding R-02). For the change control of TASK-047 (GATE-CC2) |
| F-5 | PHP's default memory limit of 128 MiB is below what GD needs for a logo at 16 megapixels | `ImageNormalizer` raises the limit for the request, bounded by the header's dimensions (review finding R-03); the release image of TASK-012 keeps `ini_set` of `memory_limit` allowed, or sets at least 384 MiB |
| F-6 | A privacy finding on test data (D-076: listed for the Owner, not passed silently) | Reported to the Owner, who accepted the exposure within the limits of D-110. The tests now use a synthetic word and an optional private list outside the repository |
| F-7 | `DatabaseTestCase::observer()` opens a new session at every call, kept until the test ends, so a loop of many calls can exhaust PostgreSQL's connections | `facts()` of `ChangeBusinessProfileTest` now reads through one session; `OwnerBootstrapTest` stays within the limit. A note for later Tasks' failure-injection loops: take one observer per comparison |
| F-8 | The workers of `ProfileChangeRaceTest` stored their logos on the real disk `media`. CI runs `composer test:race` as root (the image has no `USER`), so `MediaStore` created `storage/app/private/media` with mode 0750 owned by root; the entrypoint's `chmod` runs only at the container's start, and PHP-FPM (`www-data`) could then not write there. Every real logo upload in the browser suite failed, a server error that the client shows as uncertain: run 7, the Owner's path at the three widths | A defect of the test: `ProfileChangeScenario` now uses `Storage::fake(MediaStore::DISK)`. The cause is read from the code and the run's order (run 4 passed before this test existed); the fix passes in run 41035407222. For TASK-012: the release image fixes the owner of the media folder, and a process of another user, an operator procedure included, must not be able to create it first |
| F-9 | `tests/Browser/configuration/` signs in the people of Toko Contoh but was not in `PEOPLE` of `playwright.config.ts`, so it ran beside the project `people`, whose fixtures end those sessions: the navigation to `/login` of run 7's test at 320 × 640 | `configuration/**` added to `PEOPLE`; passes in run 41035407222 |
| F-10 | The new keyboard test (R-05) expected its mocked refusal right after the save, but the client reads RA-04 first and asks for the password, so no request left; its check that Enter sends nothing would also have passed with the action activated | The test confirms the password in the dialog, and asserts one second after Enter that neither the dialog nor a request followed; passes in run 41035407222 |
| F-11 | TASK-009's adoption of NC-13 (b), found by review finding R-27: a form page that is idle while another tab of the same browser sends a command of its family opens on that request's panel, and stays on it after the request is released, its final action looking enabled and sending nothing until "Cek status" or a reload. No fact is written wrongly. It applies to every page of `useCommand`, not only UXP-608, and its fix is outside this Task's findings | Recorded in OPEN_ITEMS T-34 (f), for TASK-011, and reported to the Owner at the stop of section 8 |

## 6. Tests

| Test | Level | What it proves |
| --- | --- | --- |
| `tests/Feature/Configuration/ConfigurationTablesTest.php` | L2 | The constraint, deferred, privilege and retention families of the five tables; `onboarding_state` RELEASED only with its release record (AC-010-08) |
| `tests/Feature/Configuration/OwnerBootstrapTest.php` | L2, procedure | OP-01: the one ACTIVE SUPER_ADMIN, the epoch, NOT_STARTED, the revision and its pointer; the link on the terminal only and working once; the audit events and the security log entry; refused with an account, a second run, another environment, a configuration that differs from the marker, invalid options; a failure after each of the eight write steps leaves nothing, prints no link and is recorded REFUSED (R-08, R-09) (AC-010-01) |
| `tests/Concurrency/BootstrapRaceTest.php` | L3 | RACE-30, bootstrap part: of two concurrent runs exactly one succeeds (AC-010-01) |
| `tests/Concurrency/ProfileChangeRaceTest.php` | L3 | RC-32: RACE-01 with and without a logo (the same file returns the original, another conflicts), RACE-02 without a logo, and its side of RACE-25: the second change against the same revision waits on the pointer's lock and is STALE_VERSION (R-01); the snapshot side of RACE-25 is TASK-028's and TASK-030's |
| `tests/Feature/Configuration/ChangeBusinessProfileTest.php` | L4 | RC-32: revision, pointer, audit, repeat; recent authentication; every other role refused; STALE_VERSION; field keys; every logo refusal of SECURITY_MODEL section 13 before any fact or file; re-encoding without metadata, orientation, random key and `sha256`; keep, replace and remove without overwriting or deleting; RD-07 for every role, private caching, never a path, NOT_FOUND for a guessed identifier; a failure after each write step, with and without a logo (TS-21, R-01); control characters and line breaks refused (R-02); a name change on a revision without an accent audited as the name alone, and an accent always required (R-07); a 16-megapixel PNG and a 16-megapixel JPEG turned by its orientation accepted under a 128 MiB limit (R-03) (AC-010-02 to AC-010-04) |
| `tests/Feature/Configuration/SettingsPagesTest.php` | L4 | The three pages' props against the allowlists; the index's entries; every other role refused; the shop in every signed-in page, none before sign-in; the initials (AC-010-05, AC-010-06) |
| `tests/Unit/Configuration/ClientNeutralTest.php` | L1 | No denied name in the sources, with a synthetic word that proves the scan and an optional private list outside the repository (D-110), and no bundled picture; no codename in the dictionary (AC-010-07) |
| `tests/Browser/configuration/profile.spec.ts` | L5 | The Owner's path through UXP-601, UXP-607 and UXP-608 at 1440 × 900, at the 200 % zoom of 1280 × 800 (640 × 400) and at 320 × 640, with axe, contrast and targets; the multipart change after the recent-authentication dialog; the accent on every page and the title with the shop; the keyboard path through the three pages and the form, Enter never activating the final action; UXS-05 with a refused logo at its field and in the summary that takes the focus (R-05); UXS-17 from a real refusal, the current values beside the form and the next save committing (R-18); the other roles; the built assets hold no denied name or picture (AC-010-05 to AC-010-07) |
| `tests/Unit/Commands/commandClient.test.ts` | L1 | The multipart form of a frozen request with a file, JSON without one |
| Changed: `CapabilityMatrixTest`, `RouteInventoryTest`, `Allowlists`, `OperationsTest`, `PrivilegeMatrixTest`, `navigation.test.ts`, `AccountPagesTest`, `OneTimeLinkTest`, `SignInTest`, `RequestsPageTest`, `shell.spec.ts`, `requests.spec.ts` | — | The new routes in the matrix and the inventory; the shared prop `shop`; RC-32 built; the five tables in the matrix; UXI-008 registered; the title ":halaman — :toko" |

## 7. Self-review (CE-40)

Before the review the whole change was read again against the contract: every item of section 4.1 is built (section 3); the stop conditions of the contract do not apply (GD has no delegates; the reading of SEC-CH-02 adds non-sensitive configuration for signed-in users only, which the review is asked to judge); no fact is written before a logo is judged; no route lacks its gate; no user-facing text is a literal; the out-of-scope items (product photos and thumbnails, the cleanup of unreferenced files, the snapshots) are not built.

## 8. Review (CE-40)

| Round | Agent and effort | Package | Tree | Time, duration | Verdict |
| --- | --- | --- | --- | --- | --- |
| 1, the class-A review | `bk-reviewer-high`, High (CE-11) | `.git/bantukasir-executor/review/TASK-010/r1`, base `befdd2e` | the tree of HEAD `be0a042` with the uncommitted records | 30 October 2026, about 10:30, 10 min 2 s | FAIL: 5 MUST-FIX, 6 SHOULD-FIX, 6 NOTE; `--check` UNCHANGED |
| 2, the targeted verification of R-01 to R-05 | `bk-reviewer-high`, High (CE-11) | `.git/bantukasir-executor/review/TASK-010/r2`, base `befdd2e`, `--previous` r1 | `30605919`, HEAD `ecbe6d5` with the uncommitted records | 30 October 2026, from about 16:59, 8 min 42 s | FAIL: R-01 to R-04 FIXED; R-05 NOT FIXED in one part, a MUST-FIX; 3 SHOULD-FIX, 6 NOTE; no regression; `--check` UNCHANGED |
| 3, the targeted check of R-05's remaining part and R-18 (D-110) | `bk-reviewer-high`, High (CE-11) | `.git/bantukasir-executor/review/TASK-010/r3`, base `befdd2e`, `--previous` r2 | HEAD `ecbe6d5` with the fixes and records uncommitted | 30 October 2026, 7 min 39 s | FAIL: R-05 and R-19 FIXED; R-18's production code holds, its test cannot pass: the new MUST-FIX R-27; 2 NOTE; nothing else broken; judged by reading (D-110); `--check` UNCHANGED |

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | MUST-FIX | No failure injection after RC-32's write steps (TS-21); no RACE-01, RACE-02 or RC-32's side of RACE-25, multipart repeats untested | Fixed: `ChangeBusinessProfileTest` fails RC-32 after each write step, without (4) and with a logo (5), and finds nothing committed and the receipt PENDING; `ProfileChangeRaceTest` runs RACE-01 (with and without a logo, the same file or another), RACE-02 and RC-32's side of RACE-25. The snapshot side of RACE-25 is TASK-028's and TASK-030's |
| R-02 | MUST-FIX | RC-32's texts accepted control characters, a NUL ending in a server error (TR-VAL-05) | Fixed: every text field must be valid UTF-8 without a control character, a line break included, since none is a multi-line note (`ProfileFields::PLAIN_TEXT` in the request and the value object); regression test with a NUL, a BEL, an ESC and a line break. No COPY_DECK key exists for it (F-4) |
| R-03 | MUST-FIX | A logo at 16 megapixels needs more than PHP's 128 MiB in GD | Fixed: `ImageNormalizer` reserves, bounded by the header's dimensions, 13 bytes a pixel and a margin, raising the request's limit when it is lower (F-5); PNG compression at zlib's default 6 (R-16). Regression test: a transparent 4000 × 4000 PNG and a 4000 × 4000 JPEG with orientation 6 under 128 MiB |
| R-04 | MUST-FIX | Client-derived test data (D-076) | Fixed: both tests use a synthetic word and an optional private list outside the repository (D-110); listed for the Owner (F-6) |
| R-05 | MUST-FIX | The pages lacked the 200 % zoom width, the keyboard path and the states UXS-05 and UXS-17 in the browser; the evidence named 360 px for 320 px | Fixed in two parts: the Owner's path at `WIDTHS[4]` too; a keyboard test through UXP-601, UXP-607 and the form of UXP-608 with Enter never activating the final action; UXS-05 with the refused logo at its field; section 6 corrected. The remaining part, UXS-05's summary that receives focus: round 2 below |
| R-06 | SHOULD-FIX | Nginx's 6 MiB applies to every route | Recorded as F-3 for TASK-012: a per-route location with the front controller's parameters is the release configuration's, and the application refuses larger bodies itself |
| R-07 | SHOULD-FIX | The form sends the rendered accent, so the first change after the bootstrap audited `accentToken` as changed; RC-32 accepted `null` | Fixed: `changedFrom()` compares rendered accents; `accentToken` is required, one of the five; regression test |
| R-08 | SHOULD-FIX | No failure injection after the bootstrap's write steps | Fixed: `OwnerBootstrapTest` fails after each of the eight steps: nothing remains, no link is printed, the run is recorded REFUSED |
| R-09 | SHOULD-FIX | A failure other than a refusal left no `OPERATOR_PROCEDURE_RUN` entry | Fixed: `OperatorProcedure` records the run REFUSED and reports the failure; proven by R-08's test |
| R-10 | SHOULD-FIX | SECURITY_MODEL's status line did not name TASK-010's amendment | Fixed |
| R-11 | SHOULD-FIX | No acceptance-criteria or Definition of Done table, no backup impact | Fixed by the record of CE-50: sections 10 to 12 |
| R-12 | NOTE | `TokoContoh::shop()` falls back to the services when earlier fixtures created accounts first | Kept and recorded: the suites of earlier Tasks create their people before any shop; installing through OP-01 first everywhere would change their account counts. On an empty database the fixture uses OP-01's Action |
| R-13 | NOTE | The reading of SEC-CH-02 changes no privacy rule | Recorded: the contract's stop condition does not apply |
| R-14 | NOTE | The environment is typed as an option, not at a prompt | Recorded for TASK-011 (OP-02, OP-03), which may add a prompt to the framework |
| R-15 | NOTE | UXP-607 shows at most 50 earlier revisions, without paging | Recorded: a shop changes its profile rarely; paging when a later change needs it |
| R-16 | NOTE | The hard end is checked only before the upload work; PNG level 9 is slow; ICC profiles are dropped | PNG compression lowered to 6 with R-03; the hard end and the colour profiles recorded |
| R-17 | NOTE | CURRENT_HANDOFF's TESTS line did not name TASK-010's run | Fixed in the record |

Round 2 (the executor checked the first row against the code and UI_STATES: it holds):

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-05, remaining | MUST-FIX | UI_STATES UXS-05 asks for "a summary at the top that receives focus". UXP-608 shows UXS-05 only through `CommandPanels`, a plain `StateNotice` after the form's card, and nothing moves the focus (`ProfileEdit.tsx`, `Panels.tsx`). `ChangePassword.tsx` and `SetPassword.tsx` use the established `ErrorSummary` (`States.tsx`). The test asserts only that "Periksa isian" is visible, so run 41035407222 cannot show it | Fixed by the Owner's answer (option b, D-110): UXP-608 shows `ErrorSummary` at the top of the page for VALIDATION_FAILED, with a message and a link for each field that has one, and in place of `CommandPanels`' notice, so the state appears once; a refusal with no message this form shows (F-4) still has the summary and takes the focus, without an empty list. Each field and the logo input have an id for the links. `.bk-error-summary a` gets `min-width: var(--touch-min)`, because the link "Logo" is narrower than the 44 px target. Regression test: the summary is focused, holds the message, its link moves the focus to the logo input; it fails without the fix, where only `StateNotice` is shown and nothing takes the focus |
| R-18 | SHOULD-FIX | UXS-17 asks for the current values beside what the user reviewed; UXP-608 shows only "Muat ulang", which discards the typed edits | Fixed by the Owner's answer: on STALE_VERSION the page reads the revision in force with a partial reload of the prop `current`, shows it as the section "Versi yang berlaku" (the existing key of COPY_DECK section 11.7) beside the form in `bk-grid-2`, one column at compact width, and the form keeps what the user typed, his chosen logo included; the next save is expected against the revision read. "Kembali ubah" is not offered in this state, so that the values stay until the next save; "Muat ulang" remains only when the read failed. The shell's guard (UXS-45) now lets a partial reload through, since it leaves nothing. Regression test: another tab really saves a version, the save is really refused 409, the new address stands beside the form, no dialog asks to leave, the form's values stay, and the next save commits |
| R-19 | SHOULD-FIX | Section 6 and the docblock of `ProfileChangeRaceTest` claimed RACE-02 with and without a logo; `test_race_02` runs without one | Fixed: section 6 and the docblock, a comment only |
| R-20 | SHOULD-FIX | `ClientNeutralTest`'s docblock says the Owner's private scan is recorded here. No such record exists, and CI scans only the synthetic word, so AC-010-07 has no recorded scan against the real name, the built assets included | Recorded by the Owner's answer, without the identity: the private scan of the sources and the tests found 0 files; the built assets were not scanned against the private list, only against the synthetic word (section 10, AC-010-07) |
| R-21 | NOTE | No L4 test repeats a multipart request through the route; each part is proven separately | Recorded |
| R-22 | NOTE | No case of an invalid UTF-8 byte by multipart; U+2028 and U+2029 are not `\p{Cc}`, so they are accepted | Recorded |
| R-23 | NOTE | The scan matches whole words; a mistyped list path passes silently in PHP while the browser test throws | Recorded |
| R-24 | NOTE | On UXP-601 and UXP-607 the keyboard test focuses the link by script, so it proves focusability and Enter, not the Tab order there | Recorded |
| R-25 | NOTE | R-11's outcome named sections 10 and 11, which were not written | R-11 corrected |
| R-26 | NOTE | Both race workers fake the same directory, so the race tests prove file records, not file survival | Recorded, no change needed |

F-8 to F-10 were read as sound by round 2.

Round 3 (the executor checked the first row against `useCommand.ts`, `unresolved.ts` and `references.ts`: it holds by reading; nothing was run):

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-27 | MUST-FIX | R-18's test could not complete: after "Kembali ubah" the form was idle, so the other tab's save put its reference in local storage, this page adopted that request's panel (`useCommand.ts`, NC-13 (b)), and stayed there after the release, so that the next click sent nothing and the wait for the 409 timed out | Fix made in the working tree, not verified: `profile.spec.ts` lets the other tab save while the form is still refused, waits until the save is enabled again, and only then returns to the draft. The test changes, no production code. **The stop:** this was the last targeted check that CE-40 allows, so a new MUST-FIX after it is a stop, with its fix unverified and no pull request. **The Owner's answer (D-110):** his option b, the fix accepted with no further targeted check and no round 4, an exception to CE-40 for this fix alone. So this MUST-FIX's fix has no targeted check and no run yet: it is proven only by the browser suite in CI on the pull request's head, a red result being a fix cycle. R-18's row above holds for a change from another device or from a tab whose form is not idle; an idle form in the same browser is F-11 |
| R-28 | NOTE | The partial reload has no `onError`, `onCancel` or `onFinish`: an answer with `errors`, or an async visit whose location answer carries a version change, would leave `reading` and its skeleton with no "Muat ulang"; a save stays safe (409, then another read) | Recorded: rare, and this page has no Inertia form |
| R-29 | NOTE | The shell guard's new condition checks `only`, not the URL: a future `router.visit(elsewhere, { only: […] })` would leave a dirty page unguarded; no such caller exists | Recorded for later Tasks: a partial reload must stay on its page, or the condition compares the path too |

## 9. Checks (CE-44 to CE-46, D-108)

| Check | Where | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck` | Local, the tree before checkpoint 1 | 09:27 | — | NOT_RUN: the native `node_modules/.bin` has no `tsc`; its run of record is CI |
| Guard (CE-31) | Local, checkpoint 1 `9aa9bc9` | 09:33 | about 6 s | PASS, no candidate |
| The command contract | CI run 40992483877 on `9aa9bc9` | 09:34:32 to 09:36:05 | about 1.5 min | FAIL at `composer analyse`: 6 PHPStan errors (a return type, an `instanceof` always true, two offsets of a `preg_match` result, two arguments of `imagecreatetruecolor`); `composer validate` and `composer lint` (Pint, the translation keys and types, the migration review, the secret scan, the audit) PASS; the later steps did not run |
| Guard (CE-31) | Local, checkpoint 2 `0f53cfa` | 09:43 | about 3 s | PASS, no candidate |
| `composer validate`, `composer lint`, `composer analyse` | CI run 40993360928 on `0f53cfa` | 09:44:35 to 09:44:53 | 18 s | PASS |
| `composer test:unit` | CI run 40993360928 on `0f53cfa` | 09:44:53 | 8 s | PASS |
| `composer test:postgres` (L2, L4, the new tests of section 6 included) | CI run 40993360928 on `0f53cfa` | 09:45:01 | 4 min 49 s | PASS |
| `composer test:race` (RACE-30 included) | CI run 40993360928 on `0f53cfa` | 09:49:50 | 31 s | PASS |
| `npm run lint` | CI run 40993360928 on `0f53cfa` | 09:50:29 | 2 s | FAIL: ESLint, 8 literals in the JSX of the three pages (`current`, `state`, ids built in attributes); the later npm steps did not run |
| Guard (CE-31) | Local, checkpoint 3 `96c6439` | 09:54 | about 4 s | PASS, no candidate |
| `composer validate`, `composer lint`, `composer analyse`, `composer test:unit` | CI run 40994436372 on `96c6439` | 09:56:26 to 09:57:11 | 45 s | PASS |
| `composer test:postgres` | CI run 40994436372 on `96c6439` | 09:57:11 | 6 min 32 s | PASS |
| `composer test:race` | CI run 40994436372 on `96c6439` | 10:03:43 | 38 s | PASS |
| `npm run lint` (ESLint, the audit) | CI run 40994436372 on `96c6439` | 10:04:30 | 6 s | PASS |
| `npm run typecheck` | CI run 40994436372 on `96c6439` | 10:04:36 | 6 s | PASS |
| `npm run test:unit` (Vitest, the multipart test included) | CI run 40994436372 on `96c6439` | 10:04:42 | 6 s | PASS |
| `npm run build` | CI run 40994436372 on `96c6439` | 10:04:48 | 6 s | PASS |
| `npm run test:e2e` | CI run 40994436372 on `96c6439` | 10:04:54 | 6 min 48 s | FAIL: 211 passed, 4 failed — a defect of the new tests only: `profile.spec.ts` and `shell.spec.ts` took `WIDTHS[0]` (1440 × 900) for the compact width, which is `WIDTHS[7]` (320 × 640); the build-assets test, the other roles' test and every earlier browser test passed |
| Guard (CE-31) | Local, checkpoint 4 `be0a042` | 10:15 | about 5 s | PASS, no candidate |
| `composer validate`, `composer lint` (Pint, the translation keys and types, the migration review, the secret scan, the audit, the Network suite: 2 tests), `composer analyse` | CI run 40996456146 on `be0a042` | 10:17:43 to 10:18:08 | 25 s | PASS |
| `composer test:unit` | CI run 40996456146 on `be0a042` | 10:18:08 | 12 s | PASS, 538 tests |
| `composer test:postgres` | CI run 40996456146 on `be0a042` | 10:18:20 | 6 min 5 s | PASS, 364 tests |
| `composer test:race` | CI run 40996456146 on `be0a042` | 10:24:25 | 36 s | PASS, 20 tests |
| `npm run lint`, `npm run typecheck`, `npm run test:unit`, `npm run build` | CI run 40996456146 on `be0a042` | 10:25:09 to 10:25:32 | 23 s | PASS |
| `npm run test:e2e` | CI run 40996456146 on `be0a042` | 10:25:33 | 5 min 46 s | PASS, 215 tests, among them the 4 of `profile.spec.ts` and the shell's per role |

The run of record for the review is 40996456146 on `be0a042`, the whole command contract green.

The fixes of the review (section 8) change PHP code, PHP tests and the browser tests, so the whole command contract runs again, in the fifth checkpoint run (CE-45, CE-46):

| Check | Where | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| Guard (CE-31) | Local, checkpoint 5 `29a9f6c` | 10:53 | about 4 s | PASS, no candidate |
| `composer validate`, `composer lint` | CI run 40999919136 on `29a9f6c` | 10:55:28 to 10:55:47 | 19 s | PASS |
| `composer analyse` | CI run 40999919136 on `29a9f6c` | 10:55:47 | 10 s | FAIL: 4 PHPStan errors in two new tests — an always-false comparison of `ini_get()`'s result (`ChangeBusinessProfileTest`), and a catch PHPStan reads as dead after `Artisan::call()` with the two statements after it (`OwnerBootstrapTest`); the later steps did not run |

That was the fifth checkpoint run, the most that CE-46 allows before the pull request: the stop `CHECKPOINT_LIMIT`, which the Owner answered with D-110 (two further runs for this Task only). The sixth run carries the four PHPStan fixes (`assertThrows()` in place of the catch; `ini_set()` with the value read), the generic scan of `ClientNeutralTest` and `profile.spec.ts` (F-6) and, before the push, two small changes that avoid static risks: no `array_values()` over a list, and `?.some()` in place of a call on a union of arrays. The seventh carries the fix of the sixth's failure:

| Check | Where | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| Guard (CE-31) | Local, checkpoint 6 `36211a3` | 15:44 | about 3 s | PASS, no candidate |
| `composer validate`, `composer lint`, `composer analyse` | CI run 41028532064 on `36211a3` | to 15:46 | — | PASS, the four errors gone |
| `composer test:unit` | CI run 41028532064 on `36211a3` | to 15:46:27 | — | PASS, `ClientNeutralTest`'s new test included |
| `composer test:postgres` | CI run 41028532064 on `36211a3` | 15:46:27 | 5 min 14 s | FAIL: 369 tests, 1 error, a defect of a new test only (finding F-7): `facts()` of `ChangeBusinessProfileTest` opened six observer sessions a call, and the failure injection's nine steps reached the server's 100 connections; its first seven steps had passed. The later steps did not run |
| Guard (CE-31) | Local, checkpoint 7 `92206a5` | 15:57 | about 3 s | PASS, no candidate |
| `composer validate`, `composer lint`, `composer analyse` | CI run 41029942148 on `92206a5` | 15:58:43 to 15:59:13 | 30 s | PASS |
| `composer test:unit` | CI run 41029942148 on `92206a5` | 15:59:13 | 14 s | PASS |
| `composer test:postgres` | CI run 41029942148 on `92206a5` | 15:59:27 | 6 min 36 s | PASS, among them every regression test of R-01 to R-03 and R-07 to R-09 |
| `composer test:race` | CI run 41029942148 on `92206a5` | 16:06:03 | 41 s | PASS, `ProfileChangeRaceTest` (R-01) included |
| `npm run lint`, `npm run typecheck`, `npm run test:unit`, `npm run build` | CI run 41029942148 on `92206a5` | 16:06:54 to 16:07:18 | 24 s | PASS |
| `npm run test:e2e` | CI run 41029942148 on `92206a5` | 16:07:19 | 6 min 53 s | FAIL: 213 passed, 4 failed, all in `tests/Browser/configuration/profile.spec.ts`; the causes are in findings F-8 to F-10 |

The seventh run was then the last that D-110 allowed, and the Task stopped with `CHECKPOINT_LIMIT` at 16:20:57, the fixes of F-8 to F-10 uncommitted: they change test code and the browser suite's configuration only, no production code. The Owner answered with one more run, the last, eight in all (D-110), resumed at 16:36:17. The eighth carries those fixes:

| Check | Where | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| Guard (CE-31) | Local, checkpoint 8 `ecbe6d5` | 16:42 | about 5 s | PASS, no candidate |
| Checkpoint 8, the command contract | CI run 41035407222 on `ecbe6d5` | 16:42:42 to 16:58:14 | 15 min 32 s | PASS, every step below |
| — start of the local environment | run 41035407222 | 16:42:45 | 57 s | PASS |
| — `composer validate`, `composer lint`, `composer analyse` | run 41035407222 | 16:43:42 to 16:44:11 | 29 s | PASS |
| — `composer test:unit` | run 41035407222 | 16:44:11 | 14 s | PASS |
| — `composer test:postgres` | run 41035407222 | 16:44:25 | 6 min 30 s | PASS |
| — `composer test:race` | run 41035407222 | 16:50:55 | 41 s | PASS |
| — `npm ci`, `npm run lint`, `npm run typecheck` | run 41035407222 | 16:51:36 to 16:51:56 | 20 s | PASS |
| — `npm run test:unit` | run 41035407222 | 16:51:56 | 6 s | PASS |
| — `npm run build` | run 41035407222 | 16:52:02 | 6 s | PASS |
| — `npm run test:e2e` | run 41035407222 | 16:52:08 | 6 min 2 s | PASS, `tests/Browser/configuration/profile.spec.ts` with the fixes of F-8 to F-10 and the regression tests of R-05 included |

The run of record before the pull request is 41035407222 on `ecbe6d5`: the whole command contract green on a head that holds the fixes of the review made so far. Round 2 then found one part of R-05 not fixed (section 8), and its fix would invalidate `npm run lint`, `npm run typecheck`, `npm run build` and `npm run test:e2e` of this run; the PHP suites would stay valid (CE-45). Run 8 was the last that the Owner's directive allows, so the Task stopped at 17:09 with `CHECKPOINT_LIMIT`, the fix not made, the records uncommitted and no pull request.

The Owner answered with option b (D-110): fix R-05 and R-18, no further checkpoint run, and CI on the pull request's head is the first run of lint, typecheck, build and the browser suite for these fixes, a red result being a fix cycle. They change `ProfileEdit.tsx`, one condition of `AppShell.tsx`, `ErrorSummary` in `States.tsx`, one rule of `components.css`, `profile.spec.ts`, and a comment of `ProfileChangeRaceTest.php`:

| Check | Where | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| The PHP checks and suites of run 41035407222 | CI run 41035407222 on `ecbe6d5` | — | — | Stay valid for the production PHP code, which these fixes do not change (CE-45); the comment changes no behaviour |
| `npm run lint`, `npm run typecheck`, `npm run test:unit`, `npm run build`, `npm run test:e2e` on the fixes | Not run before the pull request (D-110: the native toolchain has no command links, section 1, and no checkpoint run is allowed) | — | — | `NOT_RUN`; the results of run 41035407222 are invalidated (CE-45). Their run of record is CI on the pull request's head, which runs the whole command contract (CE-41) |

Round 3 then raised R-27 (section 8), and the Task stopped, the fixes and records uncommitted and no pull request. The Owner answered with option b (D-110): the test-only fix of R-27 with no further targeted check, and CI on the pull request's head as the first run of lint, typecheck, unit tests, build and the browser suite for these fixes:

| Check | Where | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run lint`, `npm run typecheck`, `npm run test:unit`, `npm run build`, `npm run test:e2e` on the final tree, with the fix of R-27 | CI on the pull request's head (CE-41; D-110) | — | — | `NOT_RUN` at this record; a commit cannot name its own run, so its result is in the pull request and the next Task records it (CE-50) |

## 10. Acceptance criteria

The PHP suites of run 41035407222 on `ecbe6d5` stay valid for the production PHP code, which no later change touched (section 9). The criteria that the browser suite or a scan of the frontend proves were proven on `ecbe6d5`, and the later fixes of `ProfileEdit.tsx`, `AppShell.tsx`, `States.tsx`, `components.css` and `profile.spec.ts` invalidate those results (CE-45), so they read `NOT_RUN` on the final tree until CI on the pull request's head.

| Criterion | Result | Proven by |
| --- | --- | --- |
| AC-010-01 | PASS | `OwnerBootstrapTest`, `BootstrapRaceTest` (RACE-30), run 41035407222 |
| AC-010-02 | PASS | `ChangeBusinessProfileTest`: every refusal, re-encoding without metadata, the random key and `sha256`, never overwritten; run 41035407222 |
| AC-010-03 | PASS | `ChangeBusinessProfileTest`, `ProfileChangeRaceTest`, run 41035407222; the browser's multipart path passed on `ecbe6d5` and runs again on the final tree |
| AC-010-04 | PASS | `ChangeBusinessProfileTest`: RD-07 for every role, private caching, never a path, `NOT_FOUND` for a guessed identifier; run 41035407222 |
| AC-010-05 | `NOT_RUN` on the final tree | `profile.spec.ts`, `SettingsPagesTest`: PASS in run 41035407222 on `ecbe6d5`; the browser suite runs again in CI on the pull request's head |
| AC-010-06 | `NOT_RUN` on the final tree | `profile.spec.ts` and `SettingsPagesTest`: PASS in run 41035407222 on `ecbe6d5`, before the fixes of R-05 and R-18; their regression tests and the fix of R-27 have not run (section 9) |
| AC-010-07 | `NOT_RUN` on the final tree, with a limit | `ClientNeutralTest` and the browser test of the build scan for a synthetic word and an optional private list: PASS in run 41035407222 on `ecbe6d5`, where CI has no private list. The Owner's private scan of the sources and the tests found 0 files. The built assets were not scanned against the private list, only against the synthetic word (R-20, D-110) |
| AC-010-08 | PASS | `ConfigurationTablesTest`: the constraint, deferred and privilege families; RELEASED only with its release record; run 41035407222 |

## 11. Definition of Done (EXECUTION_CONTEXT section 13)

| Dimension | Result | Reason |
| --- | --- | --- |
| Business | PASS | REQ-CFG-01, REQ-CFG-03 and REQ-SCOPE-03 through AC-010-01 to AC-010-04 and AC-010-08; AC-010-05 to AC-010-07 as section 10 says |
| UI and UX | `NOT_RUN` on the final tree | The three pages and the controls of contract section 4.2 passed on `ecbe6d5` at the three widths, with axe, contrast and targets; the fixes of R-05 and R-18 run first in CI on the pull request's head (D-110) |
| Backend | PASS | FormRequest allowlists, the gate of every route, the DTO allowlists, the route inventory and the capability matrix, L4 tests; run 41035407222 |
| Database | PASS | The migration review of `composer lint`, and every PostgreSQL suite applies the migration; the constraint, deferred, privilege and retention families of the five tables; run 41035407222 |
| Security | PASS | The refusals of the other roles and the combined account, THR-09, THR-10 and THR-12 as section 6 covers them, no secret, the guard and the static checks; the reading of SEC-CH-02 changes no privacy rule (R-13). The accepted exposure is D-110's |
| Data integrity | PASS | Immutable revisions and a pointer under its lock, audit and result in one transaction, RC-32's identity and repeats, the races and the failure injection of R-01 and R-08; run 41035407222 |
| Performance | PASS | Bounded queries: at most 50 earlier revisions on UXP-607 (R-15); dimensions read before decoding; the budgets are measured at TASK-048 |
| Operations | PASS | Section 12; OP-01's procedure tests; the release image's requirements in F-2, F-3, F-5 and F-8 for TASK-012 |
| Verification | `NOT_RUN` on the final tree | The command contract green in checkpoint run 41035407222 on `ecbe6d5`; the self-review; the class-A review and two targeted checks (section 8), the fix of R-27 without one by D-110. CI on the pull request's final head is the gate (CE-41) |

## 12. Backup impact (EXC-47)

| Item | Impact |
| --- | --- |
| The five new tables | Classes F and S (SCHEMA section 10), so each is in the manifest's `counts` (BACKUP_RECOVERY section 5). The manifest is built by TASK-013, which depends on this Task; nothing to change now |
| The stored files | A new kind of stored file, the logo, on the private disk `media`: `stored_files` gives what the manifest's `files` and the file check against `sha256` read (BACKUP_RECOVERY section 5). TASK-013 backs the files up, and TASK-012's release image holds the folder (F-8) |
| Runbook and status | OP-01 is the procedure OPERATIONS_RUNBOOK section 2 already names; no document of operations changes |

## 13. Guard preview of the record (CE-31, CE-50)

`bash scripts/executor/guard.sh --preview` on the working tree with this record, 30 October 2026 at 19:52: `GUARD PREVIEW PASS`, no candidate of the privacy and client-data check, so no judgment to record. Each of the eight checkpoint commits passed `guard.sh` before its push, with no candidate (section 9).
