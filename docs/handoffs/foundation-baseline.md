# WEM-2 Foundation — pre-merge closure baseline

Status: **WEM-10 in progress; candidate baseline on feat/WEM-2-foundation**, 2026-10-02. This file is an evidence-based handoff, not proof that `dev` has been updated or that the entire guest-management product is functional.

## Repository and delivery
- GitHub: `m415x/wordpress-event-manager`. Historical `main` baseline: `74116d06ccf40f3316cee7a33b034df45f8efa7b`.
- Canonical integration: `dev`. Candidate branch: `feat/WEM-2-foundation`. Open integration [draft PR #1](https://github.com/m415x/wordpress-event-manager/pull/1) is **cumulative Foundation WEM-2**, not a WEM-11-only patch.
- Remote comparison observed 2026-10-02: 198 commits ahead / 0 behind, 53 changed files versus `dev`. Recheck immediately before merging; these numbers are point-in-time only.
- WEM-3, WEM-4, WEM-5, WEM-6, WEM-7, WEM-8, WEM-9 and blocking WEM-11: **Listo** in Jira. WEM-10: **En curso**. WEM-2 parent: **En curso**. Do not mark closure complete until latest-head CI and review/merge evidence are recorded.
- WEM-10 is the approved final closure subtask: merge only after a refreshed WEM-2 acceptance review, checks and a durable baseline update. Do not merge to `main` as part of this operation.

## Foundation acceptance matrix

| WEM-2 requirement | Evidence and boundary |
| --- | --- |
| Audit initial repository state | WEM-3 Jira/source-backed historical `main` inventory; earlier activation fatal was an observation at that time, later corrected under WEM-11. |
| Compare earlier monolith with v2.2.1 | WEM-9 `docs/architecture/legacy-delta-v211-v221.md` classifies preserved/changed/removed/debt using the earlier user-supplied source snapshot. It is source analysis, not blanket runtime parity. |
| Establish delivery/source-of-truth workflow | WEM-4 `AGENTS.md`, `docs/README.md`, Git `main`/`dev`/story branch flow and Jira traceability. |
| WordPress/PHP automated tests and compact RED/GREEN | WEM-5/6 PHPUnit unit+integration configs, `scripts/tdd.sh`, `pnpm tdd:red` and `pnpm tdd`, runner classification tests. Human-observed suite results under WEM-11: unit and integration GREEN. |
| Lint, static analysis, CI | WEM-7 Composer strict, PHP syntax, PHPCS, PHPStan, GitHub Actions workflow. Scoped analysis only: PHPCS/PHPStan configuration does not audit all legacy PHP modules. Pull request CI run #163 had two completed/success jobs as inspected through GitHub connector. Recheck after later commits. |
| Neutral identity | WEM-8 `docs/architecture/naming-contract.md` and source-scanning tests enforce WEM-only identifiers across maintained source. **Breaking new-installations-only policy**; no aliases, existing installation migration, or saved data compatibility. |
| Preserve non-target behavior | WEM-9 source-delta inventory plus WEM-11 targeted registration and fail-closed changes. Source equivalence is not full end-to-end functional parity. Remaining known regressions and security boundaries documented below. |
| Publish durable fresh-chat baseline | This document plus `AGENTS.md`, docs index and handoff. Must be updated with exact merge commit / `dev` SHA after approved integration. |

## Verified WordPress runtime boundary

- Plugin entrypoint: `wordpress-event-manager.php`; directory and text domain `wordpress-event-manager`. WEM underscore-prefixed PHP classes autoload from `includes/class-*.php` with underscore-to-hyphen conversion.
- Real disposable WordPress activation on manual environment, plugin v2.2.1 Active. Normal WordPress CLI confirmed **13/13 registration checks PASS**: `invitado` post type, `evento` taxonomy; shortcodes `wem_checkin`, `wem_list`, `wem_qr_table`, `wem_qr_single`; both auth and nopriv registrations for `wem_checkin_ajax`/`wem_list_ajax`; admin metabox/save/menu hooks.
- User-supplied admin screenshots showed `Invitados` menu, guest add/editor form with metadata box and initial check-in state, event taxonomy admin form and import/export menu. This shows rendering/navigation, **not saved guest data, CSV operation or check-in workflow**.
- Until WEM-13 defines and tests event-scoped authorization, both guest AJAX operations are **deny-all** for anonymous users and logged-in site administrators: HTTP **403** and JSON error with code `guest_access_unavailable`, observed through real HTTP. All four sensitive shortcodes return a fixed temporary-unavailable message. Do not present those features as operational or relax security on merge.

## Two disposable WordPress environments

| Config | Port | Purpose |
| --- | --- | --- |
| `.wp-env.test.json` | 8890 | Manual admin UI, browser/session and HTTP smoke; local site state preserved between runs. |
| `.wp-env.phpunit.json` | 8892 | Separate WordPress Core PHPUnit site/database. **Never** run tests against manual site CLI. |

- Start manual site: `pnpm wp:start`; check `pnpm wp:status`; verify anonymous HTTP with `pnpm wp:verify:anon`; stop with `pnpm wp:stop`.
- Start PHPUnit site and restore locked Composer dependencies: `pnpm wp:phpunit:start`; then `pnpm tdd unit` and `pnpm tdd integration`; stop with `pnpm wp:phpunit:stop`.
- Configuration guard: `pnpm test:wp-isolation`; shell helper tests: `pnpm test:wp-scripts`; runner classifications: `pnpm test:runner`.
- The shared DB bug was reproduced when both workflows used the same config: manual plugin Active → PHPUnit ERROR → manual plugin Inactive. After isolation, human observed **Active → integration GREEN → Active**. Do not reintroduce shared wp-env configuration.
- Local `wp-env` and Composer operations use disposable Docker, never production. Avoid secrets in Git.

## Known deferred work and limits

- **WEM-13**: guest identity, authorization, cross-event permissions, QR/ticket-secret handling; sensitive AJAX/shortcodes remain blocked pending separate approved security contract and tests.
- **WEM-12**: CSV importer created/updated/skipped counter behavior. Historical delta indicates a source-level regression; no fix under Foundation.
- **WEM-14**: immutable append-only check-in movement record and derived current state; not implemented in Foundation.
- UI observations: mixed Spanish/English admin interface, duplicate event selection patterns and exposed default Custom Fields panel. Do not silently refactor in Foundation closure.
- CI notices: Node 20 action deprecation and an announced `ubuntu-latest` runner image migration; treat as separate maintenance follow-up if not already tracked, not as fixed by this work.
- No production deployment, migration from existing installations, complete guest data lifecycle audit, accessibility certification or public guest functionality is claimed.

## Completion checklist for WEM-10 (not yet passed)

1. Verify all WEM-3–WEM-9 and WEM-11 are still `Listo` in Jira; document WEM-2 criteria reconciliation.
2. Run/review full applicable current-head CI and final relevant local checks, maintaining manual WordPress activation.
3. Review draft PR #1 final diff, ensure no unintended compatibility aliases, secrets, open security handlers or new regressions.
4. Update this page and current handoff with **actual merge SHA** only after merging, then verify `dev` points to the integrated result.
5. Transition WEM-10 and WEM-2 to `Listo` only with evidence; leave `main` untouched.
6. New chat begins from remote `dev`: read `AGENTS.md`, `docs/README.md`, this baseline, Jira and current source; do not treat earlier chats as authoritative.
