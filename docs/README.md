# WordPress Event Manager — Documentation Index

Read `../AGENTS.md` before starting a story.

## Current baseline
- WEM-2: Foundation and v2.2.1 baseline (in progress).
- Historical product baseline: `main@74116d06ccf40f3316cee7a33b034df45f8efa7b`.
- Source-backed audit findings: [WEM-3](https://trail-running-workout-planning.atlassian.net/browse/WEM-3), closed. These are observations, not test-suite passes.
- Current engineering workflow: [WEM-4](https://trail-running-workout-planning.atlassian.net/browse/WEM-4).
- PHP/WordPress test harness, RED/GREEN runner and CI baseline: WEM-5, WEM-6 and WEM-7 completed with verified CI.
- [WEM-8 clean-break naming contract](architecture/naming-contract.md): verified neutral identity for new installations only; source regression GREEN and both GitHub Actions jobs SUCCESS (run #36881129249). WEM-8 evidence covered identity; WEM-11 separately verified plugin activation and temporary deny-all, not a completed authorization model.
- [WEM-9 historical delta](architecture/legacy-delta-v211-v221.md): **Listo** in Jira. Source-backed classification completed, with follow-up work WEM-11/12/13/14; WEM-11 supplies verified activation and registration evidence; formal review/closure is tracked in Jira before WEM-10. No WEM-9 runtime modifications.
- [WEM-11 approved fail-closed bootstrap contract](architecture/safe-bootstrap-wem11.md): **Listo** in Jira. WordPress activation, 13/13 live registration smoke, authenticated and anonymous HTTP 403 denial, unit/integration gates, Composer strict, scoped PHPCS/PHPStan, manual admin walkthrough and GitHub Actions #153 were verified on 2026-10-02. WEM-13 retains the event-scoped authorization design and release gate. [Daily wp-env commands](testing.md#disposable-wordpress-daily-commands--wem-11) are available and helper tests verified GREEN.
- [Dedicated PHPUnit database isolation](testing.md#wordpress-phpunitdatabase-isolation--wem-11-correction-2026-10-02): manual WordPress port 8890 and PHPUnit port 8892 are independent. Static check GREEN and live manual plugin state **Active → integration GREEN → Active** verified on 2026-10-02.
- [Draft PR #1](https://github.com/m415x/wordpress-event-manager/pull/1) contains all WEM-2 Foundation changes (not just WEM-11). **Do not merge until WEM-10 finishes closure.** WEM-11 completed its technical closure; WEM-10 remains Por hacer and owns final WEM-2 integration into dev.

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
The historical bootstrap fault is fixed and verified with live WordPress activation and a bounded admin walkthrough (WEM-11, 2026-10-02). Authenticated and anonymous guest AJAX routes deliberately return HTTP 403 with fixed unavailable JSON; sensitive shortcodes are fail-closed. **This does not demonstrate working public check-in or establish event-scoped authorization**: WEM-13 owns that separately. Foundation remains unmerged in draft PR #1 pending WEM-10 closure; PHPStan and PHPCS cover only their configured scopes.
