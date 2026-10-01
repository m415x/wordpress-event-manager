# WordPress Event Manager — Documentation Index

Read `../AGENTS.md` before starting a story.

## Current baseline
- WEM-2: Foundation and v2.2.1 baseline (in progress).
- Historical product baseline: `main@74116d06ccf40f3316cee7a33b034df45f8efa7b`.
- Source-backed audit findings: [WEM-3](https://trail-running-workout-planning.atlassian.net/browse/WEM-3), closed. These are observations, not test-suite passes.
- Current engineering workflow: [WEM-4](https://trail-running-workout-planning.atlassian.net/browse/WEM-4).
- Runtime-specific and test setup: WEM-5, WEM-6, WEM-7 (not yet delivered).
- Legacy naming migration: WEM-8 (not yet delivered).
- Legacy snapshot reconciliation: WEM-9 (not yet delivered).
- Foundation closure and next-chat baseline: WEM-10.

## Durable documents
- [Current handoff](handoffs/current.md) — operational state for resuming the active story.
- `architecture/` — add domain contracts only when accepted and implemented. Do not treat preliminary ideas as production design.

## Ownership
- Current source/tests: actual behavior.
- Architecture docs: approved durable invariants.
- Jira: scope, execution state, blockers, test evidence, decisions.
- Current handoff: immediate orientation only; not authority over code or Jira.
- Chat history: navigation only.

## Explicit limitations
The historical plugin does not currently boot in the observed local WordPress CLI environment. No automated test suite or CI gates have been established for this repository. Do not claim the old feature set passes until specific tests and walkthroughs provide evidence.
