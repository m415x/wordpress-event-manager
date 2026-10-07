# Current operational handoff — WEM-32 staff provisioning

Updated: 2026-10-07. This is an orientation document; remote GitHub + Jira + current source/tests remain authoritative.

## Current baseline
- Canonical integration branch: `dev`; stable `main` remains separate.
- Foundation WEM-1/WEM-2 is closed. PR #1 integrated Foundation into `dev`.
- WEM-12 CSV import accounting is **Listo** in Jira. PR #2 merged into `dev` at `20cc59a3b0c2bf1259f4e2ad716addc084ebec1a`.
- WEM-13 event-scoped staff authorization is **Listo** and integrated into `dev` via PR #3 at merge `4f63cd01ad9583153e19c0d985394ab1267a075d`; WEM-19 through WEM-24 are closed.
- The WEM-13 frozen contract remains in `docs/architecture/staff-authorization-wem13.md`.
- WEM-14 is **Listo** and integrated into `dev` via PR #4 at merge `f7992617f9a14b6ea8a2838d3575344ea5b4dbaf`.
- WEM-25 through WEM-30 are complete. WEM-25 froze the durable contract; WEM-26 implemented append-only storage; WEM-27 implemented atomic check-in and rollback/drift behavior; WEM-28 completed checkout/reentry, concurrency and outside-only event reassignment; WEM-29 integrated the WEM-13 AJAX surface; WEM-30 closed full verification, manual acceptance, CI, PR and merge.
- WEM-32 is **En curso** on `feat/WEM-32-staff-capability-provisioning`. WEM-44 freezes the provisioning contract; WEM-45–WEM-49 own implementation through closure.

## WEM-13 verified behavior
- Operational staff authority requires WordPress authentication, operation-specific WEM capability and explicit authorized `evento` term IDs.
- Global administrators have global event scope but still require a valid `invitado` with exactly one canonical event and valid transition/request integrity.
- `wem_list_ajax`, `[wem_list]`, `wem_checkin_ajax` and `[wem_checkin]` now expose only the authenticated staff/admin paths covered by the WEM-13 contract.
- Anonymous/nopriv guest-list and mutation AJAX remain fixed HTTP 403 deny-all.
- Public guest/legacy QR paths remain unavailable; WEM-13 does not implement public invitation tokens or guest self-check-in.
- Staff listing/query filters cannot expand beyond actor event scope.
- Guests with zero or multiple `evento` terms are denied operational access.
- `wem_observaciones` remains private/admin-only and is not rendered on staff operational surfaces.
- Check-in, checkout and re-entry are server-authorized and preserve denied-state isolation.
- Staff scope assignment is persisted only through `WEM_Authorization::set_authorized_event_ids()` and edited through the bounded WordPress user-profile integration.
- Roster navigation uses the real WordPress event-page permalink rather than assuming pretty permalinks.
- Successful roster check-in reloads the operational page so list and colocated detail state remain synchronized.

## WEM-14 closure evidence
- Final feature HEAD before merge: `c91220ace0b61f7b266ee26118fe987b56626663`.
- GitHub Actions run `37625472731`: `WordPress isolated integration` SUCCESS and `PHP 8.3 lint, analysis and unit` SUCCESS.
- Local gates GREEN: unit, full integration, TDD runner self-tests 15/15, local workflow helper, DB isolation, Composer strict, PHPCS and configured PHPStan.
- Manual anonymous HTTP guards: `wem_checkin_ajax` and `wem_list_ajax` returned HTTP 403 with the fixed deny JSON.
- Authenticated browser walkthrough verified Event A check-in → checkout → reentry, UI state updates, Event B cross-scope denial and private-observation non-disclosure.
- Manual environment schema presence was verified explicitly as `wp_wem_guest_movements`; no historical movement reconstruction was performed.
- PR #4 merged into `dev` at `f7992617f9a14b6ea8a2838d3575344ea5b4dbaf`.

## WEM-32 frozen provisioning contract

- Operational authority remains capability-based; preset or WordPress role names never authorize business operations.
- Canonical provisioning states are None, Viewer and Operator. Viewer grants `wem_view_event_guests`; Operator grants view plus `wem_operate_event_guests`.
- Presets modify only WEM-managed user capabilities and preserve existing WordPress roles and non-WEM capabilities.
- Non-canonical external capability combinations must be detected without silent normalization; only an explicit preset application may converge them.
- Event scope remains independent and all writes stay behind `WEM_Authorization::set_authorized_event_ids()`.
- Scope without capability never authorizes; revoke takes effect immediately even when scope remains persisted.
- `manage_options` remains WEM-13's global-admin boundary.
- WEM-32 requires no provisioning installer by default. Activation/reactivation/deactivation must not grant, revoke, normalize or rewrite user provisioning. A newly discovered need for versioned provisioning state requires a fresh decision before implementation.
- The `class-cpt-manager.php` use of `administrator` as a role-like capability is not a WEM-32 blocker and remains WEM-35 debt; Viewer/Operator do not gain generic CPT administration authority.
- Durable contract: `docs/architecture/staff-provisioning-wem32.md`.

## Fresh-chat behavior
A fresh chat should not carry operational workflow in its prompt. Follow `AGENTS.md`.

- Start from remote `dev`, current CI/PR state, source/tests, docs and Jira.
- Use GitHub/Jira connectors directly for remote evidence.
- Ask the user only for genuinely local/manual evidence or a real product/domain decision.
- Do not repeat tests/CI/walkthroughs merely to recreate exact-SHA evidence.
- Treat WEM-14 as integrated baseline. Select the next story from current Jira state; do not reopen WEM-14 unless a verified regression or approved follow-up requires it.

## Current testing workflow
See `docs/testing.md` for authoritative commands.

- Manual WordPress: `.wp-env.test.json`, port 8890.
- PHPUnit WordPress: `.wp-env.phpunit.json`, port 8892.
- Never run WordPress Core PHPUnit against the manual site.
- Remote CI status should be inspected directly through GitHub tooling.

## Product boundaries still in force
- WEM-8 compatibility remains **new-installations-only**; no implicit legacy migration or aliases.
- Public invitation/token/QR capability is still not implemented.
- WEM-14 runtime movement persistence and authenticated AJAX integration are implemented, verified and integrated into `dev`.
- No production deployment or production guest data is implied by completed local/CI evidence.

## WEM-14 active contract

- Dedicated WEM table: append-only accepted movement ledger and historical source of truth.
- Existing post meta remains a mutable current-state projection for efficient reads.
- Every movement has UUID movement_id plus a monotonic internal sequence; occurred_at alone does not define total order.
- guest_id + captured canonical event_term_id are immutable historical scope.
- The operational guest is the serialization unit for state reread, validation, append and projection update.
- checkin, checkout and reentry are distinct movement types.
- Event reassignment is allowed only while outside and never rewrites historical movement event_term_id.
- Append + projection update are one transaction; partial commits are forbidden.
- Ledger/projection drift causes controlled failure; there is no silent autorepair.
- The ledger is accepted-only. Rejected attempts remain outside WEM-14.
- New installations only; no reconstruction, backfill or historical migration.


## WEM-14 implementation state

- Dedicated table: `{$wpdb->prefix}wem_guest_movements`, keyed by monotonic `sequence` with unique `movement_id`.
- Accepted movement types are `checkin`, `checkout` and `reentry`; source is currently `staff_web`; decision is `accepted`.
- `WEM_Movement_Service` serializes the guest row with `SELECT ... FOR UPDATE`, rereads projection/history, rejects drift, validates transition, appends one movement and updates projection in one SQL transaction.
- Failed projection writes roll back the movement; post-meta cache is invalidated after rollback so WordPress reads do not expose stale transactional state.
- Event reassignment is explicit, guest-serialized, allowed only outside, changes current taxonomy scope and clears the mutable movement projection without rewriting prior ledger facts.
- `wem_checkin_ajax` keeps WEM-13 authentication/capability/event-scope and nonce checks before delegating to the movement service.
- Rejected authorization/request/transition attempts are not ledger movements.
- Public/anonymous/legacy QR behavior remains outside WEM-14 and must stay fail-closed.
