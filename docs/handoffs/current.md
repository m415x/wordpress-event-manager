# Current operational handoff — WEM-12 CSV import accounting

Updated: 2026-10-05. Source of truth remains remote GitHub + Jira; reread both before resuming.

## Current source state
- Repository: `m415x/wordpress-event-manager`; canonical integration branch: `dev`; stable `main` unchanged.
- Active branch: `fix/WEM-12-csv-import-accounting`, based on `dev@0d9cc403f5916a8c61a22629177c713c0afe011b`.
- Draft PR: #2, targeting `dev`.
- WEM-12 is `En curso`; WEM-15, WEM-16 and WEM-17 are `Listo`; WEM-18 is `En curso`.

## Implemented WEM-12 contract
- The importer now distinguishes `created` and `updated` explicitly instead of inferring the result from a truthy post ID.
- Guest identity remains scoped by title + event.
- Existing skip behavior, persisted `wem_*` guest fields, the CSV header `titulo,nombre,organizacion,mesa,evento,observaciones,checkin`, and established encoding normalization remain unchanged.
- No schema migration, authorization change, public guest exposure or movement-history redesign is part of WEM-12.
- WEM-11 fail-closed behavior remains mandatory until WEM-13 defines and verifies real event-scoped authorization.

## Verified evidence
- Test-only RED commit: `9e3467ffe1d262c69fa282623083c0ca76f44fe3`; human execution confirmed the expected behavioral RED.
- Minimal runtime fix commit: `e937d7df7e3ad907f55840f1d57a1f2de7d850ec`; focused integration test GREEN.
- Regression coverage commit: `a04be24cd2cdecd5df5d0b4677d8a095818247c5`; multirow accounting, persistence, event isolation and CP1252/already-UTF-8 checks GREEN.
- Full local gates GREEN: standalone unit, WordPress integration, runner self-tests 15/15, wp-env DB isolation, Composer strict validation, configured PHPCS and PHPStan.
- Manual environment isolation preserved: PHPUnit WordPress on 8892; manual WordPress on 8890.
- Manual runtime recovered non-destructively and returned HTTP 200 on 8890 with plugin v2.2.1 active.
- Synthetic CSV walkthrough GREEN: 1 created, 1 updated, 0 skipped; the existing guest remained one record with updated persisted values.
- `pnpm wp:verify:anon` GREEN: both sensitive AJAX actions returned HTTP 403 and fixed deny-all JSON.

## Closure still required
1. Treat the documentation commit that updates this handoff as the final candidate HEAD.
2. Verify GitHub Actions on that exact PR HEAD; both PHP quality/unit and isolated WordPress integration jobs must succeed.
3. Mark PR #2 ready, merge to `dev` only if the verified HEAD is unchanged and mergeable.
4. Record exact CI run + candidate SHA + merge SHA in Jira WEM-18/WEM-12.
5. Transition WEM-18 and WEM-12 to `Listo` only after integration evidence is durable.

## Developer commands
- Manual WordPress: `pnpm wp:start`, `pnpm wp:status`, `pnpm wp:verify:anon`, `pnpm wp:stop`.
- Isolated PHPUnit: `pnpm wp:phpunit:start`, `pnpm tdd unit`, `pnpm tdd integration`, `pnpm wp:phpunit:stop`.
- Focused behavior: `pnpm tdd:red tests/Integration/CsvImportAccountingTest.php` and `pnpm tdd tests/Integration/CsvImportAccountingTest.php`.
- Mock/helper gates: `pnpm test:runner`, `pnpm test:wp-isolation`.

## Deferred work
- WEM-13: event-scoped authorization and permitted guest/staff actions.
- WEM-14: immutable movement history and current-state projection.
- No WEM-12 work should relax security guards or introduce backward-compatibility migration.
