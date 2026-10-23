# Current operational handoff — WEM-74 I1 integrated; WEM-73 custody gates open

Updated: 2026-10-22. Start from GitHub `dev`, Jira and current code; this handoff is orientation, not higher authority.

## 2026-10-23 — extraordinary P0 priority canceled; ordinary WEM-75 Harness v2 resumed

- Client canceled improvements for the immediate 2026-10-23 event. This cancels **urgency only**, not the functional contracts or prior RED/GREEN evidence. P0 branch `feat/WEM-preissued-invitations-p0` is preserved without merge, closure or real-event issuance. Last user-observed focal GREEN: `PreissuedPrintableMappingTest.php` at `ce13f8d4cc545158debf55341b4c7bb73af5ecee`; substantial P0 gates remain (real InnoDB concurrency, independent QR decode, HTTP verification, private custody).
- `dev` includes the infrastructure reuse policy via PR #13, merge `32c0fbd6651f67296100b769021bbd020fb3f3dd`. No new Docker/wp-env project per story or branch; preserve manual/PHPUnit database isolation and other projects' resources.
- WEM-75 is `En curso`, feature branch `feat/WEM-75-atomic-reservations` compared with current `dev`: ahead 7 / behind 5, so reconcile integration base later without attributing prior gates to new SHA. WEM-81/T1 is `En curso` in Jira: candidate implementation `6e6251767febfd633f82e5e30753f02866d02a5a`; local T1 GREEN was user-reported, but exact executed RED/GREEN Jira comments and closure conditions require reconciliation, not manufactured status.
- WEM-82/T2 remains `Por hacer` in Jira. Initial-reservation RED **test committed** at `bd83826747c5893004f45b82de634c2a1896665b`, but an actually executed behavioral RED was not recorded in Jira at this checkpoint. Precise restart: reconcile T1 evidence, transition T2 appropriately, execute the existing focused T2 RED on an already owned isolated InnoDB environment, record assertion/output+SHA, then implement the smallest GREEN without rerunning valid T1 probes. Jira WEM-75 comment 14031; WEM-82 comment 14032.
- Approved WEM-75 dependency sequence: T1 WEM-81 → T2 WEM-82 → T3 WEM-83 (concurrency) → T4 WEM-84 (admin cancellation) → T5 WEM-85 (reassignment) → T6 WEM-86 (participation cancellation) → T7 WEM-87 (closure). Follow parent WEM-80 and WEM-41 dependencies for subsequent stories. WEM-34 stays `En Revisión` on its own contract and is not forced into this critical path.
- This checkpoint is documentation-only; no production code, Docker services, tests or functional contracts were changed. Historical sections below may reference earlier plans; this dated decision takes precedence for current priority, but actual GitHub/Jira state must be rechecked before implementation.

## Canonical integration baseline

- `dev`: WEM-73 PR #8 merged at `404748ee759480c3f4ae013ed2bdcaa16f1411bf`.
- WEM-73 candidate HEAD: `9ae68e79182b`, GitHub Actions `39624597563` GREEN 2/2. Post-merge push `php-quality.yml` run #1022 GREEN 2/2; Jira 13821.
- WEM-73 source-focused ZIP: 69 runtime files; SHA-256 `f24f5933227f8faf987a8c93fd3c99a592b27debdcb1e431b4c134635a266b6e`. Acceptance: clean WP ZIP installation, two-guest private URL/CSV, duplicate operation denied; separate persistence / SQL recon / encrypted archive restore synthetic PASS. See [durable WEM-73 contract](../architecture/private-bulk-invitations-wem73.md) and Jira 13792–13795, 13809–13821.
- WEM-33 PR #6 merged previously at `79b6ec`; WEM-33 public read-only bearer resolver is implemented. Do not resurrect outdated branch-only WEM-33 closure claims.
- Earlier integrated stories WEM-2, 12, 13, 14 and 32 remain covered by the docs index and architecture contracts. `main` is not the integration target.
- This documentation branch `docs/WEM-73-post-merge-handoff` is docs-only and **must not** be mistaken for the unchanged candidate HEAD/ZIP. No QR integration was made.

## Documentation-only checkpoint — Docker/wp-env reuse policy (2026-10-23)

- Separate documentation branch `docs/WEM-infrastructure-reuse-policy` from current `dev`, independent of P0 preissued-invitation implementation and WEM-82/T2.
- `AGENTS.md`, `docs/agent-harness.md`, `docs/testing.md`: reuse-first inventory and ownership, disposable database isolation, manual/PHPUnit separation, CI preference, narrow exceptions, and selective retirement requiring evidence and express authorization. This is **documentation only**; no Docker/Compose action, script/config change, RED/GREEN, or application-code change is claimed.
- Fresh executors must read the policy on integrated `dev`, not infer a specific Compose project or port from temporary observations. Docs branch requires scoped diff review, PR/CI and integration verification before this becomes canonical on `dev`.
- P0 print work remains urgent and independent, with no authorization to emit real credentials until WEM-73 custody gates are met.

## Functional delivery status

The verified minimum WEM-73 path is WP-CLI administrative bulk credential issuance and private retrieval of an Excel-compatible CSV of invitation bearer URLs, intended after CSV guest import. It is not an admin GUI with bulk selection, not QR images, and not seat selection. Each invitation is for one person.

Public bearer possession grants only the WEM-33 minimal read-only public view. Check-in remains an authenticated staff operation under WEM-13/14. No public CSV retrieval and no token output in normal CLI success logs.

**No operational GO for real guest credentials yet.** Appoint custodian and replacement; confirm server PHP 8.3, private persistent POSIX volume, 0700/0600, administrator/host access, off-host encrypted backup with key custody, consistent MySQL backup/restore, sensitive HTTP/access/error log policy, confidential delivery channel, documented retention and deletion deadline. Synthetic Docker volume restore is not a full WordPress restore. See WEM-73 contract/runbook.

## WEM-74 I1 — integrated into dev

- WEM-41 approved I1 implements physical event tables, structured table reservations, consent, participation status, administrative audit storage, and a composite reservation-to-table/event FK. Runtime reservation transactions, public selection, administrative flows and privacy projection remain I2–I6, **not** completed by I1. The audit table is storage; append-only behavior must be enforced at the authorized writer in the later service.
- PR [#12](https://github.com/m415x/wordpress-event-manager/pull/12) **merged into `dev`**: candidate HEAD `ef194be4270ebfbdc23898fecd1455b5158098b2`, merge commit `d5fd385dae4ba75f5fadbade6afa31cc660ba6ac`. Candidate [GitHub Actions #1175](https://github.com/m415x/wordpress-event-manager/actions/runs/39711127415) GREEN both PHP 8.3 lint/analysis/unit and isolated WordPress integration. A separate workflow associated with the merge SHA had **not** been returned when checked on 2026-10-22; do not invent post-merge GREEN.
- Fresh-instance complete local PHPUnit gate: `d89704a1789e4f0e408a9846273e7b081ff8f076`, 73 unit/542 assertions and 142 integration/3950 assertions = 215 tests/4492 assertions, PASS. Product FK persistent InnoDB probe `5d3a908a9bca3b6061ade504184796894dd6b3ef` PASS: valid same-event reservation, cross-event/nonexistent-table rejection, retained rows on schema rerun, ON UPDATE/DELETE RESTRICT. Subsequent first-run movement focal on `58754f957775fdea69e28d9b3f3100cb7b2fd064` PASS 3 tests/27 assertions. These are **different commits** and do not prove repeated-run PHPUnit reliability.
- General PHPUnit reusable environment exhibited movement-ledger count/projection drift on subsequent runs; isolated first-run tests passed without changes to movement business logic. WEM-72 owns repeatability investigation; do not erase or reset that environment to generate artificial GREEN.
- Disposable Docker WEM-74 resources cleaned selectively after inventory and backups: 6 Compose projects, 18 containers, 12 exclusive volumes, 6 networks removed; WEM-73 custody and PHPUnit general preserved. Host-side evidence is retained under `~/WEM74-CLOSURE-EVIDENCE-20261021`; manifest `evidence-manifest-v3.sha256` verified PASS. Evidence is local, not stored in Git or the Docker volumes.
- After merge: reconcile Jira WEM-74, verify any later `dev` CI and handoff; do not claim a merge-SHA post-merge run without evidence. Fresh focal movement probe on `58754f9` used an additional disposable local WP environment (port 9011) that must be selectively removed by exact Compose project after preserving its logs; this resource is NOT included in the six previously cleaned instances. Dependencies I2–I6 remain not implemented.

## Immediate next work

1. WEM-73 technical and administrative closure is complete in Jira (`Listo`); PR #8 and documentation PR #9 are merged. Documentation merge on `dev`: `7c752a9348ad`, post-merge documentary CI #1026 GREEN 2/2 (Jira 13836–13839). Operational custody gates for real guest credentials remain open independently; **no production GO**.
2. WEM-41 I1 is now being implemented in WEM-74, PR #12 Draft. Recheck remaining dependent stories and boundaries in Jira. Historical instruction: after this bounded docs-only harness reconciliation is integrated, resume [WEM-41](https://trail-running-workout-planning.atlassian.net/browse/WEM-41) discovery for the individual table-selection MVP. WEM-41 was `Por hacer` at the 2026-10-21 audit; recheck Jira issue, related stories, approvals and scope before implementation. Do not create duplicate seating stories or implement WEM-72.
3. Preserve WEM-33/34/73 contracts and existing WEM-73 functional evidence. No QR, seating behavior or guest self check-in is part of this documentation change.
4. Before cleanup, inventory ignored/untracked ZIPs, logs, databases and test helpers; preserve needed safe evidence and keep tokens, private CSVs and production data out of Git. The workflow is in [agent harness](../agent-harness.md).

## RED/GREEN evidence and fresh-chat continuity

For every bounded TDD subtask, record Jira RED and GREEN comments **as they occur**, each with issue/subtask key, relevant HEAD SHA, exact focused test/command, observed expected-failure reason or passing summary, environment, and any blocker/next bounded action. Do not record generic invented PASS, tokens, guest PII, credentials or full bearer logs. An infrastructure failure is ERROR, never legitimate RED. For audit/docs-only work, log verification and disposition without manufacturing TDD RED. Link PR, CI run and ZIP digest to their exact SHAs; leave a fresh-chat-safe handoff before context loss.

## Test environment and constraints

- Manual WordPress `.wp-env.test.json`: 8890; PHPUnit `.wp-env.phpunit.json`: 8892, isolated. See `docs/testing.md`.
- Verified WEM-73 custody used an isolated synthetic `wp-env` at 8898 with Docker named volume; it must not be interpreted as a durable production deployment.
- WEM-8 new-installation-only boundary; WEM-13 staff event scope and anonymous protections remain required. PHP minimum in the merged plugin is 8.3.
- No blanket proof of infrastructure request-log redaction, protected real-event delivery, backup key escrow, or post-event destruction yet.
