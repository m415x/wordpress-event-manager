# Current operational handoff — post WEM-13

Updated: 2026-10-06. This is an orientation document; remote GitHub + Jira + current source/tests remain authoritative.

## Current baseline
- Canonical integration branch: `dev`; stable `main` remains separate.
- Foundation WEM-1/WEM-2 is closed. PR #1 integrated Foundation into `dev`.
- WEM-12 CSV import accounting is **Listo** in Jira. PR #2 merged into `dev` at `20cc59a3b0c2bf1259f4e2ad716addc084ebec1a`.
- WEM-13 event-scoped staff authorization is implementation-complete on `feat/WEM-13-event-scoped-authorization`; T1–T6 (WEM-19 through WEM-24) are reconciled for closure.
- The frozen contract remains in `docs/architecture/staff-authorization-wem13.md`.
- WEM-14 remains **Por hacer** and is the next independent security/history story to evaluate after WEM-13 is merged.

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
- Before starting the next story, inspect Jira WEM-14 completely and reconcile it against current `dev`.

## Current testing workflow
See `docs/testing.md` for authoritative commands.

- Manual WordPress: `.wp-env.test.json`, port 8890.
- PHPUnit WordPress: `.wp-env.phpunit.json`, port 8892.
- Never run WordPress Core PHPUnit against the manual site.
- Remote CI status should be inspected directly through GitHub tooling.

## Product boundaries still in force
- WEM-8 compatibility remains **new-installations-only**; no implicit legacy migration or aliases.
- Public invitation/token/QR capability is still not implemented.
- WEM-14 has not introduced append-only movement history yet.
- No production deployment or production guest data is implied by completed local/CI evidence.
