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
- **WEM-12 CSV import accounting** is **Listo** in Jira. It was integrated into `dev` via PR #2 on 2026-10-05 (merge `20cc59a3b0c2bf1259f4e2ad716addc084ebec1a`) after candidate CI, post-merge CI, full local gates and synthetic walkthrough were GREEN. WEM-15 through WEM-18 are also `Listo`. The fix distinguishes created versus updated results explicitly while preserving title+event identity, CSV header/encoding behavior and WEM-11 fail-closed boundaries.
- **WEM-13 event-scoped staff authorization** is **Listo** and integrated into `dev` via PR #3 at merge `4f63cd01ad9583153e19c0d985394ab1267a075d`. WEM-19 through WEM-24 cover the frozen contract, authorization kernel, staff-scoped listing, state transitions, staff assignment and final WEM-11 boundary reconciliation. Public invitation/token/QR behavior remains out of scope and fail-closed.
- **WEM-14 immutable movement history** is **Listo** and integrated into `dev` via PR #4 at merge `f7992617f9a14b6ea8a2838d3575344ea5b4dbaf`. WEM-25 through WEM-30 cover the frozen contract, append-only storage, atomic transitions, guest serialization, drift fail-closed behavior, checkout/reentry cycles, outside-only event reassignment, WEM-13-scoped AJAX integration and final closure. Exact-head GitHub Actions run `37625472731` passed both required jobs before merge.
- **WEM-32 staff capability provisioning** is **Listo** and integrated into `dev` via PR #5 at merge `6c7faff1f304e267dd74b8d2ddc142e64eeb3d0b`. WEM-44 through WEM-49 are complete. Final feature HEAD `c2b8f01c451741979bfd75e88391724e0df8064d` passed both required GitHub Actions jobs in run `37645179459`; real WordPress lifecycle acceptance and anonymous HTTP guards were GREEN.
- **WEM-33 secure public invitation credentials** is a **closure candidate** on `feat/WEM-33-secure-public-invitations`. WEM-56 through WEM-61 are complete; WEM-62 is in progress. Last fully verified implementation candidate passed both required GitHub Actions jobs in run `37795451779`. Final manual lifecycle/browser acceptance and exact-HEAD closure remain before merge to `dev`.

## Durable documents
- [Testing](testing.md) — initial PHPUnit harness, Docker commands, limits and pending integration coverage.
- [Foundation integrated baseline](handoffs/foundation-baseline.md) — WEM-2 acceptance evidence, actual merge SHA, security/compatibility limitations, isolated test environments and fresh-chat bootstrap.
- [WEM-13 staff authorization contract](architecture/staff-authorization-wem13.md) — frozen event-scoped staff/admin authorization model; public surfaces remain fail-closed.
- [WEM-14 immutable movement ledger contract](architecture/immutable-movement-ledger-wem14.md) — frozen append-only history, mutable projection, transaction, serialization and new-installation-only rules.
- [WEM-32 staff capability provisioning contract](architecture/staff-provisioning-wem32.md) — frozen None/Viewer/Operator provisioning model over WEM-13 capabilities, non-canonical state handling, lifecycle and activation/deactivation boundaries.
- [WEM-33 public invitation credential contract](architecture/public-invitation-wem33.md) — frozen 256-bit opaque bearer credential, digest-only persistence, immutable generations, single-active lifecycle, dedicated public resolver, minimal read-only projection and no-leak HTTP boundary.
- [Current handoff](handoffs/current.md) — operational state for resuming the active story.
- `architecture/` — add domain contracts only when accepted and implemented. Do not treat preliminary ideas as production design.

## Fresh-chat entry point
The chat prompt should stay minimal. The agent must reconstruct operational detail from `AGENTS.md`, this index, current source/tests, the current handoff and Jira.

- Remote GitHub/Jira evidence is collected directly through connectors; do not ask the user to act as a proxy for CI, PR, branch or Jira status when those systems are accessible.
- Human intervention is reserved for genuinely local/manual evidence or product decisions.
- Story readiness is determined from Jira description, comments, relationships, blockers and durable evidence — not issue number/backlog order alone.
- See [Testing](testing.md) for the current executable commands and environment boundaries.

## Ownership
- Current source/tests: actual behavior.
- Architecture docs: approved durable invariants.
- Jira: scope, execution state, blockers, test evidence, decisions.
- Current handoff: immediate orientation only; not authority over code or Jira.
- Chat history: navigation only.

## Explicit limitations
The historical bootstrap fault is fixed and verified with live WordPress activation and a bounded admin walkthrough (WEM-11, 2026-10-02). Authenticated and anonymous guest AJAX routes deliberately return HTTP 403 with fixed unavailable JSON; sensitive shortcodes are fail-closed. **This does not demonstrate working public check-in or establish event-scoped authorization**: WEM-13 owns that separately. Foundation has been merged into `dev` via PR #1 and WEM-10/WEM-2 are closed. WEM-12 and WEM-15–18 are also closed. WEM-13 is integrated and closed. WEM-14 is integrated and closed under its frozen contract: movement persistence and authenticated operational integration are implemented and verified. The WEM-13 authorization boundary and public fail-closed behavior remain mandatory. PHPStan and PHPCS cover only their configured scopes.
