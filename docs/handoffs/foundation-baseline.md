# WEM-2 Foundation — pre-merge closure baseline

Status: **WEM-2 Foundation integrated into `dev` on 2026-10-02**. This file is the resulting durable technical baseline; it does not imply that the full guest-management product is functional.

## Repository and delivery
- GitHub: `m415x/wordpress-event-manager`. Historical `main` baseline: `74116d06ccf40f3316cee7a33b034df45f8efa7b`.
- Canonical integration: `dev`. Merged Foundation branch: `feat/WEM-2-foundation`. [PR #1](https://github.com/m415x/wordpress-event-manager/pull/1) was merged into `dev` at merge commit **`0b32514cb20ded251e21da70cc1b3f8681c1b6fd`** (conventional merge, preserving branch commits). This integrated cumulative WEM-2 and WEM-11 work, not a standalone WEM-11 patch.
- Before integration, the branch contained 201 commits ahead / 0 behind and 54 changed files versus the previous `dev`. After GitHub-reported merge, a remote comparison of `feat/WEM-2-foundation...dev` returned ahead=1, behind=0, changed files=0, consistent with one merge commit and no remaining branch diff. Do not use these historical counts as ongoing branch state.
- WEM-3 through WEM-9 and WEM-11: **Listo** in Jira as verified in the WEM-10 audit. WEM-10 and WEM-2 were **En curso at merge time**; close them after post-merge verification, recording status updates in Jira. Jira, not this static document, is the status authority.
- WEM-10 is the approved final closure subtask: audit and CI checks completed before merge; PR #1 was made ready and merged after check #169 success on expected feature HEAD. **`main` was not changed** by this merge. The post-merge documentation commits on `dev` require their own CI tracking; do not attribute the earlier feature-head CI to these later docs changes.

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

## WEM-10 closure audit (execution evidence)

- [x] Jira WEM-3–WEM-9 and WEM-11 confirmed `Listo` before merge; WEM-2 acceptance dimensions reconciled with tracked implementation and documentation.
- [x] WordPress activation/registration walkthrough and AJAX deny-all transport verified; unit/integration gates, configured PHP lint/static checks, runner helper and static DB isolation gates GREEN as recorded in WEM-11.
- [x] Pull-request GitHub Actions #169 (run ID `37015712363`) completed **success** in both jobs on feature HEAD `82cb9a99a6517839f559abb547b1eccfb2a8334e`.
- [x] Final GitHub diff reviewed as cumulative WEM-2 Foundation; no untracked credentials or production env files in the changed-path set; AJAX/shortcode/QR fail-closed guards inspected.
- [x] PR #1 made ready, GitHub reported mergeable=true, merged into `dev` with expected feature-head SHA guard, merge SHA `0b32514cb20ded251e21da70cc1b3f8681c1b6fd`.
- [x] Post-merge compare confirms `dev` ahead by one merge commit with no remaining feature diff.
- [ ] Confirm GitHub Actions on the final post-merge documentation commit in `dev` and record status, then close WEM-10 and WEM-2 in Jira.
- [ ] Freeze the final post-closure `dev` SHA and ensure the next fresh chat uses remote `dev`, not the old branch.

## Fresh-chat bootstrap after Foundation

1. Start from **remote `dev`**, inspect `AGENTS.md`, `docs/README.md`, this baseline, `docs/architecture/` and Jira; do not rely on the conversation transcript.
2. Verify actual `dev` HEAD/CI and inspect source/tests before selecting subsequent work. WEM-12 (CSV accounting), WEM-13 (authorization) and WEM-14 (append-only check-in history) remain separate deferred issues. Do not infer a product release order from this handoff.
3. Retain WEM-8 new-installations-only compatibility boundary. **Do not loosen temporary deny-all guards until WEM-13 explicitly freezes, implements and validates actor/event authorization.**
4. Use separated manual and PHPUnit wp-env configurations, `pnpm tdd` and test-first workflows; do not point PHPUnit at production or the manual WordPress database.
