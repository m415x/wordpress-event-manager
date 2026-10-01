# WordPress Event Manager — Documentation Index

Read `../AGENTS.md` before starting a story.

## Current baseline
- WEM-2: Foundation and v2.2.1 baseline (in progress).
- Historical product baseline: `main@74116d06ccf40f3316cee7a33b034df45f8efa7b`.
- Source-backed audit findings: [WEM-3](https://trail-running-workout-planning.atlassian.net/browse/WEM-3), closed. These are observations, not test-suite passes.
- Current engineering workflow: [WEM-4](https://trail-running-workout-planning.atlassian.net/browse/WEM-4).
- PHP/WordPress test harness, RED/GREEN runner and CI baseline: WEM-5, WEM-6 and WEM-7 completed with verified CI.
- [WEM-8 clean-break naming contract](architecture/naming-contract.md): approved for new installations only; test-first identity migration is in progress, with an intentional RED pending verification.
- Legacy snapshot reconciliation: WEM-9 (not yet delivered).
- Foundation closure and next-chat baseline: WEM-10.

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
The historical plugin does not currently boot in the observed local WordPress CLI environment. Automated unit/integration tests and scoped static/CI gates are established. A newly committed WEM-8 expected-failure contract test may temporarily make CI RED until the naming implementation reaches GREEN. Do not claim the old feature set passes until specific tests and walkthroughs provide evidence.
