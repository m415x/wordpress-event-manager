# Current operational handoff — WEM-12 CSV import accounting

Updated: 2026-10-05. Source of truth remains remote GitHub + Jira; reread both before resuming.

## Current source state
- Repository: `m415x/wordpress-event-manager`; canonical integration branch: `dev`; stable `main` unchanged.
- WEM-12 was merged through PR #2 into `dev` at `20cc59a3b0c2bf1259f4e2ad716addc084ebec1a`.
- Verified candidate HEAD: `ec2891426220295b99c928f9369fdbe6c7ddf3df`; GitHub Actions run #183 (`37311008966`) completed successfully before merge.
- WEM-15, WEM-16 and WEM-17 are `Listo`; WEM-18/WEM-12 await only final documentation-CI/Jira reconciliation.

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

## Integration evidence
- PR #2 was marked ready only after run #183 completed successfully on the exact candidate SHA.
- PR #2 merged to `dev` with merge SHA `20cc59a3b0c2bf1259f4e2ad716addc084ebec1a`.
- This post-merge documentation reconciliation must receive its own successful `dev` CI before WEM-18/WEM-12 are transitioned to `Listo`.

## Developer commands
- Manual WordPress: `pnpm wp:start`, `pnpm wp:status`, `pnpm wp:verify:anon`, `pnpm wp:stop`.
- Isolated PHPUnit: `pnpm wp:phpunit:start`, `pnpm tdd unit`, `pnpm tdd integration`, `pnpm wp:phpunit:stop`.
- Focused behavior: `pnpm tdd:red tests/Integration/CsvImportAccountingTest.php` and `pnpm tdd tests/Integration/CsvImportAccountingTest.php`.
- Mock/helper gates: `pnpm test:runner`, `pnpm test:wp-isolation`.

## Deferred work
- WEM-13: event-scoped authorization and permitted guest/staff actions.
- WEM-14: immutable movement history and current-state projection.
- No WEM-12 work should relax security guards or introduce backward-compatibility migration.
