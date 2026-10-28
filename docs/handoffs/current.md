# Current operational handoff — WEM-76/I3 candidate implemented, review and integration pending

> **WEM-75/WEM-87 integrated closure evidence (2026-10-25):** PR [#15](https://github.com/m415x/wordpress-event-manager/pull/15) merged into `dev` at `006f838fb31f65e3edf11013018487e8d7dd0173`. Post-merge GitHub Actions [run 40147674595](https://github.com/m415x/wordpress-event-manager/actions/runs/40147674595) finished **GREEN 2/2**: PHP 8.3 lint/analysis/unit and WordPress isolated integration. Local partitioned integration gate: Core 140 tests / 3948 assertions, durable InnoDB 27 tests / 281 assertions, syntax PASS (Jira 14193); full suite partitioned sequentially to avoid WP test transaction state leaking into durable transactional assertions. The runner classifier fails closed on unknown tests and is covered in CI. WEM-81–86 focal RED/GREEN and T7 evidence are in Jira. This is **server-side seating reservation/administrative-service and test-harness delivery**, not the full WEM-41 guest-facing selection UI or operational GO for real invitations. Preserve separate PHPUnit/manual WordPress environments and credential custody requirements.

Updated: 2026-10-27. Start from GitHub `dev`, active WEM-76 branch, Jira and current code; this handoff is orientation, not higher authority.

## Canonical integration baseline

- Canonical integrated functional merge: WEM-75/I2 [PR #15](https://github.com/m415x/wordpress-event-manager/pull/15), merge SHA `006f838fb31f65e3edf11013018487e8d7dd0173`. [CI post-merge run 40147674595](https://github.com/m415x/wordpress-event-manager/actions/runs/40147674595) **GREEN 2/2 on that merge SHA only**.
- Subsequent documentation-only commits: `38c1b7f177716801673ea6def2b81197825718e7` (handoff), `353f759f8b2b74aaaa4da57ae4c02216724cea2d` (testing). These are **not** the tested merge SHA, and the earlier CI must not be attributed to either commit or later documentation HEAD.
- WEM-74/I1 [PR #12](https://github.com/m415x/wordpress-event-manager/pull/12) merged at `d5fd385dae4ba75f5fadbade6afa31cc660ba6ac`; foundational storage completed. WEM-75/I2 adds transactional single-seat reservation, race/overcapacity protection, administrative cancellation/reassignment, and participation cancellation with audit. **Neither I1 nor I2 delivers public seating projection, privacy controls or guest selector.**
- Prior credential milestones remain distinct: WEM-33 bearer/read-only invitation; WEM-34 QR-local packaging; WEM-73 private bulk URL/CSV and unresolved real-event custody. WEM-72 repeatability remains separate; WEM-75 runner partition did not change movement business logic or production transaction guards.
- **Active feature candidate, NOT integrated:** [WEM-76/I3](https://trail-running-workout-planning.atlassian.net/browse/WEM-76), branch `feat/WEM-76-bearer-seating-privacy`. T1–T5 implement bearer-scoped authorization, availability, visibility consent, privacy-preserving members, and own initial reservation via I2. WEM-92/T5 previously completed. T6 WEM-93 security and REST candidate is functionally implemented with local gates; WEM-33-specific regression, Jira closure, branch review, PR/CI and merge are separate pending steps. I4 WEM-77 UI is NOT started.

## WEM-76/I3 branch-only acceptance snapshot (2026-10-27)

- **Branch:** `feat/WEM-76-bearer-seating-privacy`. Functional bootstrap registration SHA `ada90f2a6de153ae1a24dba7512a05ddca7b2632`; transactional POST through I2 SHA `6b03e494d79abef9dd28c888f95a040f1c3ff7c8`; terminal operational test script SHA `1a6b973d8819b166edff2a867b8aed7899b45e24`. These are successive candidate SHAs, **not merged dev or post-merge CI**.
- **T1–T5:** bearer identity and event scope; private event-scoped availability; privacy consent; anonymized table membership (without structured verified names, member output stays `Integrante privado`); own initial reservation through canonical I2 transactions with guest-lock token revalidation, race tests and no cross-event mutation. Jira WEM-92/T5 finished independently; avoid changing I2 or WEM-33.
- **T6 WEM-93:** bearer only from `X-WEM-Invitation` HTTP header, request-attempt metering by validated server peer, generic pre-authorization 403, no-store/no-referrer/noindex, scoped post-auth 409 and 201, no bearer/guest PII in HTTP projections. Public REST `GET availability`, `GET members`, `POST reservations` registered via plugin bootstrap. PHP suite `pnpm tdd` reported GREEN after bootstrap (WEM-93 Jira 14458; exact runner log not supplied). Tests for duplicates, revoked bearer, missing peer, foreign event, exceeded quota reported GREEN on branch. No production GO.
- **HTTP anonymous:** isolated WordPress port 8892 after plugin activation, `bash scripts/check-seating-public-http.sh http://localhost:8892` **PASS 3/3**, Jira **14467**.
- **HTTP authenticated:** `bash scripts/check-seating-authorized-http.sh http://localhost:8892` **PASS 5/5**: GET availability 200; GET members 200; POST first reservation 201; duplicate POST 409; GET members 200; privacy and secure headers, Jira **14470**. Fixture exclusively for isolated testing, script Git Bash path correction SHA `d1ceb30bc0011589ea95a2e54c8b8b245ecf3e04`.
- **Fixture cleanup/staff regressions:** `bash scripts/check-seating-post-http-regressions.sh http://localhost:8892` **PASS** guest_posts/event_terms/actors/event_tables 0 each, staff `wem_checkin_ajax` 403 and `wem_list_ajax` 403, Jira **14472**. No Docker lifecycle operations.
- **Remaining:** specifically verify WEM-33 minimal public invitation resolver plus malformed/unknown/rotated/revoked token behavior and read-only privilege boundary against this exact branch HEAD; reconcile WEM-93 Jira closeout, inspect overall diff, create/review WEM-76 PR against `dev`, await CI exact HEAD SHA, integrate only after independent approval. WEM-77/UI, real-event custody and operational GO are out of scope.

## Docker/wp-env infrastructure reuse

Harness v2 in [AGENTS.md](../../AGENTS.md), [agent harness](../agent-harness.md) and [testing guide](../testing.md) is integrated policy: inventory and reuse before startup, manual/PHPUnit database isolation, avoid a Compose project per story, prefer CI, preserve evidence and obtain express authorization for selective destructive retirement. The policy is not a pending documentation branch. No Docker lifecycle operation is authorized by this handoff.

## Functional delivery status

The verified minimum WEM-73 path is WP-CLI administrative bulk credential issuance and private retrieval of an Excel-compatible CSV of invitation bearer URLs, intended after CSV guest import. It is not an admin GUI with bulk selection, not QR images, and not seat selection. Each invitation is for one person.

Public bearer possession grants only the WEM-33 minimal read-only public view. Check-in remains an authenticated staff operation under WEM-13/14. No public CSV retrieval and no token output in normal CLI success logs.

**No operational GO for real guest credentials yet.** Appoint custodian and replacement; confirm server PHP 8.3, private persistent POSIX volume, 0700/0600, administrator/host access, off-host encrypted backup with key custody, consistent MySQL backup/restore, sensitive HTTP/access/error log policy, confidential delivery channel, documented retention and deletion deadline. Synthetic Docker volume restore is not a full WordPress restore. See WEM-73 contract/runbook.

## Immediate next work

1. **Active candidate:** [WEM-76/I3](https://trail-running-workout-planning.atlassian.net/browse/WEM-76) implemented on feature branch, **not merged**. Complete WEM-33-specific regression, review WEM-93 conditions and finish PR/CI preparation against remote `dev`; do not start WEM-77.
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
