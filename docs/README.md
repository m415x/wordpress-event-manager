# WordPress Event Manager — Documentation Index

Read `../AGENTS.md` before starting a story.

## Current baseline
- WEM-2: Foundation and v2.2.1 baseline (in progress).
- Historical product baseline: `main@74116d06ccf40f3316cee7a33b034df45f8efa7b`.
- Source-backed audit findings: [WEM-3](https://trail-running-workout-planning.atlassian.net/browse/WEM-3), closed. These are observations, not test-suite passes.
- Current engineering workflow: [WEM-4](https://trail-running-workout-planning.atlassian.net/browse/WEM-4).
- PHP/WordPress test harness, RED/GREEN runner and CI baseline: WEM-5, WEM-6 and WEM-7 completed with verified CI.
- [WEM-8 clean-break naming contract](architecture/naming-contract.md): verified neutral identity for new installations only; source regression GREEN and both GitHub Actions jobs SUCCESS (run #36881129249). Functional activation and authorization remain unverified.
- [WEM-9 historical delta](architecture/legacy-delta-v211-v221.md): **Listo** in Jira. Source-backed classification completed, with follow-up work WEM-11/12/13/14; WEM-11 blocks WEM-10 until actual plugin activation and hook registration are verified. No WEM-9 runtime modifications.
- [WEM-11 approved fail-closed bootstrap contract](architecture/safe-bootstrap-wem11.md): En curso. Focused bootstrap, administrative hooks, guest shortcodes and JSON AJAX deny-all integration tests GREEN by user report. Actual disposable WordPress plugin activation verified on 2026-10-02; both anonymous HTTP AJAX requests returned real 403 with fixed deny-all JSON. Authenticated real HTTP verification and final gates still pending. [Daily wp-env commands](testing.md#disposable-wordpress-daily-commands--wem-11) are available for local reuse; helper tests not yet run.
- [Dedicated PHPUnit database isolation](testing.md#wordpress-phpunitdatabase-isolation--wem-11-correction-2026-10-02): manual WordPress port 8890 remains distinct from PHPUnit port 8892; `pnpm test:wp-isolation` is a static guard. Runtime isolation still requires user verification after RED/GREEN implementation.
- Foundation closure and next-chat baseline: WEM-10, blocked by WEM-11.

## Durable documents
- [Testing](testing.md) — initial PHPUnit harness, Docker commands, limits and pending integration coverage.
- [Current handoff](handoffs/current.md) — operational state for resuming the active story.
- `architecture/` — add domain contracts only when accepted and implemented. Do not treat preliminary ideas as production design.

## Ownership
- Current source/tests: actual behavior.
- Architecture docs: approved durable invariants.
- Jira: scope, execution state, blockers, test evidence, decisions.
- Current handoff: immediate orientation only; not authority over code or Jira.
- Chat history: navigation only.

## Explicit limitations
The historical plugin did not boot in the observed local WordPress CLI environment. WEM-8 naming identity tests are now GREEN, and the isolated metadata integration and scoped static/CI gates are verified. This does **not** verify plugin activation, complete feature behavior, or authorization safety; require explicit integration and walkthrough evidence before making those claims.
