# Evidence — TASK-019 CSV product import

| | |
| --- | --- |
| Base | `ac654db` on `develop` (TASK-018's squash merge) |
| Branch | `task/TASK-019-csv-import` |
| Instruction | Continuous execution D-101, D-105, D-107, D-109, D-117; selected by CE-10 on 2 November 2026 |
| Executor | `bk-executor-high`, model `claude-opus-5-5`, effort High (CE-11) |
| Review class | A |

## 1. Checks (EXC-61)

Dates and times in Asia/Jakarta. Synthetic data only (Toko Contoh).

| Check | Where it ran | Date and time | Duration | Result |
| --- | --- | --- | --- | --- |
| `composer validate` | CI fast run 41360710125, head `fac9497` | 2 Nov 2026 06:12 | under 1 s | PASS |
| `composer lint`, with the translation checks and the migration review | the same | 06:12 | 16 s | PASS |
| `composer analyse`, level 9 and the project's rules | the same | 06:12 | 8 s | PASS |
| `composer test:unit`, 840 tests, `CsvDialectTest` among them | the same | 06:12 | 10 s | PASS |
| `npm run lint` | the same | 06:13 | 29 s | PASS |
| `npm run typecheck` | the same | 06:13 | 3 s | PASS |
| `npm run test:unit`, `csvImport.test.ts` among them | the same | 06:13 | 4 s | PASS |
| `tests/Concurrency/CsvImportRaceTest.php`, 6 tests | the same | 06:13 | 6 s | PASS |
| `tests/Feature/Catalog/CsvImportTest.php`, 18 tests, the 5,000 rows with their barcodes among them | the same | 06:15 | 56 s | PASS |
| `tests/Feature/Catalog/CsvImportTableTest.php`, 3 tests | the same | 06:15 | 1 s | PASS |
| `tests/Feature/Access/CapabilityMatrixTest.php`, `RouteInventoryTest.php`, `tests/Feature/Catalog/CatalogPagesTest.php` | the same | 06:13 to 06:15 | 102 s | PASS |
| The whole of `composer test:postgres` and `composer test:race` | CI on the pull request's final head (D-117) | — | — | `NOT_RUN` |
| `npm run build`, the browser suite with `tests/Browser/catalog/import.spec.ts`, the image checks with RR-01's limits | CI on the pull request's final head (D-117) | — | — | `NOT_RUN` |
| The guard's preview and the guard | local, on each checkpoint | 2 Nov 2026 | under 10 s each | PASS; one candidate judged, section 5 |

Six earlier fast runs failed at the first failing step, each fixed within the Task: 41355753754 (Pint), 41356432668 and 41357207622 (PHPStan), 41357955637 (a defect of a test: a header that only one separator splits), 41358660449 (ESLint), 41359537662 (a test's type). Seven of the ten allowed fast runs are used (CE-46). The native TypeScript and ESLint are not installed on this machine, so those checks ran in CI only.
