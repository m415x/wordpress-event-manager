# Current operational handoff — WEM-14 closure

Updated: 2026-10-07. This is an orientation document; remote GitHub + Jira + current source/tests remain authoritative.

## Current baseline
- Canonical integration branch: `dev`; stable `main` remains separate.
- Foundation WEM-1/WEM-2 is closed. PR #1 integrated Foundation into `dev`.
- WEM-12 CSV import accounting is **Listo** in Jira. PR #2 merged into `dev` at `20cc59a3b0c2bf1259f4e2ad716addc084ebec1a`.
- WEM-13 event-scoped staff authorization is **Listo** and integrated into `dev` via PR #3 at merge `4f63cd01ad9583153e19c0d985394ab1267a075d`; WEM-19 through WEM-24 are closed.
- The WEM-13 frozen contract remains in `docs/architecture/staff-authorization-wem13.md`.
- WEM-14 is **En curso** on `feat/WEM-14-immutable-movement-ledger`, currently at closure subtask WEM-30.
- WEM-25 through WEM-29 are complete. WEM-25 froze the durable contract; WEM-26 implemented append-only storage; WEM-27 implemented atomic check-in and rollback/drift behavior; WEM-28 completed checkout/reentry, concurrency and outside-only event reassignment; WEM-29 integrated the WEM-13 AJAX surface.

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

## Closure evidence
- Final functional candidate before documentation: `b0393a3e14b67db78449f1dbe960b62e48ab392b`.
- GitHub Actions on that SHA: PHP 8.3 lint/analysis/unit GREEN and isolated WordPress integration GREEN.
- Manual anonymous HTTP guards: `wem_checkin_ajax` and `wem_list_ajax` returned HTTP 403 with the fixed deny JSON.
- Authenticated browser walkthrough verified Event A listing, guest detail, check-in, checkout, re-entry, Event B cross-scope denial and private-observation non-disclosure.
- Manual follow-up defects were converted to regression tests and fixed:
  - stale roster check-in UI → page reload on success;
  - hardcoded pretty-permalink navigation → `get_permalink()` + `add_query_arg()`.
- Final browser recheck confirmed both fixes.

## Fresh-chat behavior
A fresh chat should not carry operational workflow in its prompt. Follow `AGENTS.md`.

- Start from remote `dev`, current CI/PR state, source/tests, docs and Jira.
- Use GitHub/Jira connectors directly for remote evidence.
- Ask the user only for genuinely local/manual evidence or a real product/domain decision.
- Do not repeat tests/CI/walkthroughs merely to recreate exact-SHA evidence.
- Resume WEM-14 from Jira and the immutable movement-ledger contract. Do not bypass WEM-13 authorization or invent legacy history.

## Current testing workflow
See `docs/testing.md` for authoritative commands.

- Manual WordPress: `.wp-env.test.json`, port 8890.
- PHPUnit WordPress: `.wp-env.phpunit.json`, port 8892.
- Never run WordPress Core PHPUnit against the manual site.
- Remote CI status should be inspected directly through GitHub tooling.

## Product boundaries still in force
- WEM-8 compatibility remains **new-installations-only**; no implicit legacy migration or aliases.
- Public invitation/token/QR capability is still not implemented.
- WEM-14 runtime movement persistence and authenticated AJAX integration are implemented. WEM-30 still owns full-suite/quality gates, real HTTP/manual boundary verification, CI/PR evidence, merge to `dev` and final Jira closure.
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

## WEM-30 closure still required

Run the authoritative commands from `docs/testing.md` on the final branch HEAD. Required evidence includes:

- full unit and integration GREEN;
- helper regression checks;
- Composer strict validation;
- configured PHPCS and PHPStan gates;
- real HTTP anonymous guards on the manual environment;
- bounded authenticated browser walkthrough for checkin → checkout → reentry and cross-event denial;
- exact-head GitHub Actions success, PR review/merge evidence, and post-merge reconciliation in Jira/docs.

Do not reuse pre-documentation evidence as final-head closure evidence if a relevant code/configuration change occurs.
