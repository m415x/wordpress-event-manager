# WordPress Event Manager — Documentation Index

Read `../AGENTS.md` before starting a story.

## Current baseline
- WEM-2: Foundation and v2.2.1 baseline **integrated into `dev` via [PR #1](https://github.com/m415x/wordpress-event-manager/pull/1)** on 2026-10-02 (merge `0b32514cb20ded251e21da70cc1b3f8681c1b6fd`) and subsequently closed in Jira after post-merge CI verification.
- Historical product baseline: `main@74116d06ccf40f3316cee7a33b034df45f8efa7b`.
- Source-backed audit findings: [WEM-3](https://trail-running-workout-planning.atlassian.net/browse/WEM-3), closed. These are observations, not test-suite passes.
- Current engineering workflow: [WEM-4](https://trail-running-workout-planning.atlassian.net/browse/WEM-4).
- PHP/WordPress test harness, RED/GREEN runner and CI baseline: WEM-5, WEM-6 and WEM-7 completed with verified CI.
- [WEM-8 clean-break naming contract](architecture/naming-contract.md): verified neutral identity for new installations only; source regression GREEN and both GitHub Actions jobs SUCCESS (run #36881129249). WEM-8 evidence covered identity; WEM-11 separately verified plugin activation and temporary deny-all, not a completed authorization model.
- [WEM-9 historical delta](architecture/legacy-delta-v211-v221.md): **Listo** in Jira. Source-backed classification completed, with follow-up work WEM-11/12/13/14; WEM-11 provided and closed verified activation and registration evidence before the Foundation merge. No WEM-9 runtime modifications.
- [WEM-11 approved fail-closed bootstrap contract](architecture/safe-bootstrap-wem11.md): **Listo** in Jira. WordPress activation, 13/13 live registration smoke, authenticated and anonymous HTTP 403 denial, unit/integration gates, Composer strict, scoped PHPCS/PHPStan, manual admin walkthrough and GitHub Actions #153 were verified on 2026-10-02. WEM-13 retains the event-scoped authorization design and release gate. [Daily wp-env commands](testing.md#disposable-wordpress-daily-commands--wem-11) are available and helper tests verified GREEN.
- [Dedicated PHPUnit database isolation](testing.md#wordpress-phpunitdatabase-isolation--wem-11-correction-2026-10-02): manual WordPress port 8890 and PHPUnit port 8892 are independent. Static check GREEN and live manual plugin state **Active → integration GREEN → Active** verified on 2026-10-02.
- [Merged PR #1](https://github.com/m415x/wordpress-event-manager/pull/1) integrated all WEM-2 Foundation changes, including WEM-11, into `dev`; WEM-10 and WEM-2 are closed in Jira. `main` remains unchanged.
- **WEM-12 CSV import accounting** is implemented on `fix/WEM-12-csv-import-accounting` and pending final PR/CI integration. The fix distinguishes created versus updated results explicitly while preserving title+event identity, the established CSV header, encoding behavior and WEM-11 fail-closed security boundaries. Focused RED→GREEN, regression tests, full local gates and a synthetic manual walkthrough are GREEN.

## Durable documents
- [Testing](testing.md) — initial PHPUnit harness, Docker commands, limits and pending integration coverage.
- [Foundation integrated baseline](handoffs/foundation-baseline.md) — WEM-2 acceptance evidence, actual merge SHA, security/compatibility limitations, isolated test environments and fresh-chat bootstrap.
- [Current handoff](handoffs/current.md) — operational state for resuming the active story.
- `architecture/` — add domain contracts only when accepted and implemented. Do not treat preliminary ideas as production design.

## Ownership
- Current source/tests: actual behavior.
- Architecture docs: approved durable invariants.
- Jira: scope, execution state, blockers, test evidence, decisions.
- Current handoff: immediate orientation only; not authority over code or Jira.
- Chat history: navigation only.

## Explicit limitations
The historical bootstrap fault is fixed and verified with live WordPress activation and a bounded admin walkthrough (WEM-11, 2026-10-02). Authenticated and anonymous guest AJAX routes deliberately return HTTP 403 with fixed unavailable JSON; sensitive shortcodes are fail-closed. **This does not demonstrate working public check-in or establish event-scoped authorization**: WEM-13 owns that separately. Foundation has been merged into `dev` via PR #1 and WEM-10/WEM-2 are closed. WEM-12 is the active closeout until its final PR/CI integration is complete. PHPStan and PHPCS cover only their configured scopes.
