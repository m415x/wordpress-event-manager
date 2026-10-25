# Current operational handoff — WEM-75/I2 integrated; WEM-76/I3 next candidate

> **WEM-75/WEM-87 integrated closure evidence (2026-10-25):** PR [#15](https://github.com/m415x/wordpress-event-manager/pull/15) merged into `dev` at `006f838fb31f65e3edf11013018487e8d7dd0173`. Post-merge GitHub Actions [run 40147674595](https://github.com/m415x/wordpress-event-manager/actions/runs/40147674595) finished **GREEN 2/2**: PHP 8.3 lint/analysis/unit and WordPress isolated integration. Local partitioned integration gate: Core 140 tests / 3948 assertions, durable InnoDB 27 tests / 281 assertions, syntax PASS (Jira 14193); full suite partitioned sequentially to avoid WP test transaction state leaking into durable transactional assertions. The runner classifier fails closed on unknown tests and is covered in CI. WEM-81–86 focal RED/GREEN and T7 evidence are in Jira. This is **server-side seating reservation/administrative-service and test-harness delivery**, not the full WEM-41 guest-facing selection UI or operational GO for real invitations. Preserve separate PHPUnit/manual WordPress environments and credential custody requirements.

Updated: 2026-10-25. Start from GitHub `dev`, Jira and current code; this handoff is orientation, not higher authority.

## 2026-10-23 — extraordinary P0 priority canceled; ordinary WEM-75 Harness v2 resumed

- Client canceled improvements for the immediate 2026-10-23 event. This cancels **urgency only**, not the functional contracts or prior RED/GREEN evidence. P0 branch `feat/WEM-preissued-invitations-p0` is preserved without merge, closure or real-event issuance. Last user-observed focal GREEN: `PreissuedPrintableMappingTest.php` at `ce13f8d4cc545158debf55341b4c7bb73af5ecee`; substantial P0 gates remain (real InnoDB concurrency, independent QR decode, HTTP verification, private custody).
- `dev` includes the infrastructure reuse policy via PR #13, merge `32c0fbd6651f67296100b769021bbd020fb3f3dd`. No new Docker/wp-env project per story or branch; preserve manual/PHPUnit database isolation and other projects' resources.
- WEM-75 is `En curso`, feature branch `feat/WEM-75-atomic-reservations` compared with current `dev`: ahead 7 / behind 5, so reconcile integration base later without attributing prior gates to new SHA. WEM-81/T1 is `En curso` in Jira: candidate implementation `6e6251767febfd633f82e5e30753f02866d02a5a`; local T1 GREEN was user-reported, but exact executed RED/GREEN Jira comments and closure conditions require reconciliation, not manufactured status.
- WEM-82/T2 remains `Por hacer` in Jira. Initial-reservation RED **test committed** at `bd83826747c5893004f45b82de634c2a1896665b`, but an actually executed behavioral RED was not recorded in Jira at this checkpoint. Precise restart: reconcile T1 evidence, transition T2 appropriately, execute the existing focused T2 RED on an already owned isolated InnoDB environment, record assertion/output+SHA, then implement the smallest GREEN without rerunning valid T1 probes. Jira WEM-75 comment 14031; WEM-82 comment 14032.
- Approved WEM-75 dependency sequence: T1 WEM-81 → T2 WEM-82 → T3 WEM-83 (concurrency) → T4 WEM-84 (admin cancellation) → T5 WEM-85 (reassignment) → T6 WEM-86 (participation cancellation) → T7 WEM-87 (closure). Follow parent WEM-80 and WEM-41 dependencies for subsequent stories. WEM-34 stays `En Revisión` on its own contract and is not forced into this critical path.
- This checkpoint is documentation-only; no production code, Docker services, tests or functional contracts were changed. Historical sections below may reference earlier plans; this dated decision takes precedence for current priority, but actual GitHub/Jira state must be rechecked before implementation.

## Canonical integration baseline

- Canonical integrated functional merge: WEM-75/I2 [PR #15](https://github.com/m415x/wordpress-event-manager/pull/15), merge SHA `006f838fb31f65e3edf11013018487e8d7dd0173`. [CI post-merge run 40147674595](https://github.com/m415x/wordpress-event-manager/actions/runs/40147674595) **GREEN 2/2 on that merge SHA only**.
- Subsequent documentation-only commits: `38c1b7f177716801673ea6def2b81197825718e7` (handoff), `353f759f8b2b74aaaa4da57ae4c02216724cea2d` (testing). These are **not** the tested merge SHA, and the earlier CI must not be attributed to either commit or later documentation HEAD.
- WEM-74/I1 [PR #12](https://github.com/m415x/wordpress-event-manager/pull/12) merged at `d5fd385dae4ba75f5fadbade6afa31cc660ba6ac`; foundational storage completed. WEM-75/I2 adds transactional single-seat reservation, race/overcapacity protection, administrative cancellation/reassignment, and participation cancellation with audit. **Neither I1 nor I2 delivers public seating projection, privacy controls or guest selector.**
- Prior credential milestones remain distinct: WEM-33 bearer/read-only invitation; WEM-34 QR-local packaging; WEM-73 private bulk URL/CSV and unresolved real-event custody. WEM-72 repeatability remains separate; WEM-75 runner partition did not change movement business logic or production transaction guards.
- **Next candidate, not started:** [WEM-76/I3](https://trail-running-workout-planning.atlassian.net/browse/WEM-76) (`Por hacer`, I2 dependency satisfied): bearer-scoped seating read and own-initial-reservation boundary, privacy consent and minimized member projection. Read [approved WEM-41 contract](../architecture/seating-reservations-wem41.md), then confirm Jira comments, blockers and latest remote `dev` before proposing implementation. I4 public Spanish selector follows I3; I5 admin management may proceed separately after I2.

## Docker/wp-env infrastructure reuse

Harness v2 in [AGENTS.md](../../AGENTS.md), [agent harness](../agent-harness.md) and [testing guide](../testing.md) is integrated policy: inventory and reuse before startup, manual/PHPUnit database isolation, avoid a Compose project per story, prefer CI, preserve evidence and obtain express authorization for selective destructive retirement. The policy is not a pending documentation branch. No Docker lifecycle operation is authorized by this handoff.

## Functional delivery status

The verified minimum WEM-73 path is WP-CLI administrative bulk credential issuance and private retrieval of an Excel-compatible CSV of invitation bearer URLs, intended after CSV guest import. It is not an admin GUI with bulk selection, not QR images, and not seat selection. Each invitation is for one person.

Public bearer possession grants only the WEM-33 minimal read-only public view. Check-in remains an authenticated staff operation under WEM-13/14. No public CSV retrieval and no token output in normal CLI success logs.

**No operational GO for real guest credentials yet.** Appoint custodian and replacement; confirm server PHP 8.3, private persistent POSIX volume, 0700/0600, administrator/host access, off-host encrypted backup with key custody, consistent MySQL backup/restore, sensitive HTTP/access/error log policy, confidential delivery channel, documented retention and deletion deadline. Synthetic Docker volume restore is not a full WordPress restore. See WEM-73 contract/runbook.

## Immediate next work

1. **Candidate:** [WEM-76/I3 — Bearer-scoped table members and privacy](https://trail-running-workout-planning.atlassian.net/browse/WEM-76), currently `Por hacer`. Verify live Jira status, accepted product decisions, dependency/blockers, current `dev`, and [WEM-41 I3 contract](../architecture/seating-reservations-wem41.md) before beginning a bounded RED/GREEN cycle. I3 is not implemented.
2. **Closed upstream:** WEM-74/I1 and WEM-75/I2 are `Listo`; do not reopen them, rerun unchanged functional gates, or resurrect historical draft-PR / pending-I1 instructions. I2's tests and server-side services are complete, not public authorization or UI.
3. **Security:** maintain WEM-33 active bearer and minimal own-guest projection independently of the new I3 seating boundary; preserve WEM-34 QR security, WEM-73 private CSV/operational custody, WEM-72 movement repeatability, and event-scoped staff/check-in authorization. No operational GO for real bearer distribution without custody gates.
4. **Environment:** apply Harness v2 reuse-first policy, protect local files and secrets, never remove Docker resources without inventory and approval. No new stack merely for WEM-76.

## RED/GREEN evidence and fresh-chat continuity

For every bounded TDD subtask, record Jira RED and GREEN comments **as they occur**, each with issue/subtask key, relevant HEAD SHA, exact focused test/command, observed expected-failure reason or passing summary, environment, and any blocker/next bounded action. Do not record generic invented PASS, tokens, guest PII, credentials or full bearer logs. An infrastructure failure is ERROR, never legitimate RED. For audit/docs-only work, log verification and disposition without manufacturing TDD RED. Link PR, CI run and ZIP digest to their exact SHAs; leave a fresh-chat-safe handoff before context loss.

## Test environment and constraints

- Manual WordPress `.wp-env.test.json`: 8890; PHPUnit `.wp-env.phpunit.json`: 8892, isolated. See `docs/testing.md`.
- Verified WEM-73 custody used an isolated synthetic `wp-env` at 8898 with Docker named volume; it must not be interpreted as a durable production deployment.
- WEM-8 new-installation-only boundary; WEM-13 staff event scope and anonymous protections remain required. PHP minimum in the merged plugin is 8.3.
- No blanket proof of infrastructure request-log redaction, protected real-event delivery, backup key escrow, or post-event destruction yet.
