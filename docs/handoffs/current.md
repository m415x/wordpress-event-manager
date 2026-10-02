# Current operational handoff — WEM-2 Foundation closeout

Updated: 2026-10-02. This document describes **integrated source and pending verification**, not a release certification. For the full contractual acceptance matrix see [Foundation baseline](foundation-baseline.md), the [docs index](../README.md), [AGENTS.md](../../AGENTS.md) and Jira.

## Source and status
- Repository: `m415x/wordpress-event-manager`. Canonical development branch: **`dev`**. Stable `main` was not touched.
- Foundation branch `feat/WEM-2-foundation` was merged via [PR #1](https://github.com/m415x/wordpress-event-manager/pull/1), conventional merge SHA `0b32514cb20ded251e21da70cc1b3f8681c1b6fd`, GitHub reported merged=true.
- Post-merge GitHub comparison `feat/WEM-2-foundation...dev` was ahead=1, behind=0, 0 changed files (the merge commit). Subsequent documentation commits on `dev` update the baseline; recheck latest remote `dev` SHA and CI when starting a fresh chat.
- Jira WEM-3 through WEM-9 and blocking WEM-11 were confirmed `Listo` immediately before integration. WEM-10 and parent WEM-2 were `En curso` at merge time, with **post-merge CI and formal Jira closure pending**. Do not infer Jira status without rereading it.

## Verified evidence
- WEM-11 real WordPress activation of plugin v2.2.1 on disposable site; 13/13 normal WordPress CLI checks passed for CPT, taxonomy, shortcodes, auth/nopriv AJAX actions and administrative hooks.
- Screenshots showed Invitados creation screen/metabox, Eventos taxonomy admin page and Importar/Exportar menu. These certify page availability/rendering only, not guest save, CSV import or check-in workflows.
- Real anonymous and logged-in administrator HTTP POST requests for both guest AJAX actions returned **403** and fixed JSON error code `guest_access_unavailable` when plugin active. Four guest-facing shortcodes return a fixed unavailable message. These interfaces are **not operational** pending WEM-13.
- PHPUnit unit and WordPress integration suites GREEN by human execution. PHP syntax gated by `pnpm tdd`; Composer strict validation, scope-limited PHPCS and PHPStan GREEN; local shell runner and wp-env isolation checks GREEN.
- Critical database isolation regression fixed: `.wp-env.test.json` manual WordPress on port **8890** versus `.wp-env.phpunit.json` PHPUnit on **8892**. Human verified manual plugin **Active → integration GREEN → Active**.
- PR CI run **#169**, run ID `37015712363`, was verified as `success` with both `PHP 8.3 lint, analysis and unit` and `WordPress isolated integration` completed/success, on feature head immediately before merge. It does **not** prove success of later post-merge documentation commits on `dev`.

## Developer commands
Run from repository root in Git Bash with Docker Desktop available.

- Manual WordPress: `pnpm wp:start`, `pnpm wp:status`, `pnpm wp:verify:anon`, `pnpm wp:stop`.
- Isolated PHPUnit: `pnpm wp:phpunit:start` (initial startup/locked Composer dependencies), `pnpm tdd unit`, `pnpm tdd integration`, `pnpm wp:phpunit:stop`.
- Focused behavior: `pnpm tdd:red tests/Unit/ExampleTest.php` for assertion RED; `pnpm tdd tests/Integration/ExampleTest.php` for GREEN and PHP syntax.
- Mocked helpers: `pnpm test:runner`, `pnpm test:wp-scripts`; static wp-env boundary: `pnpm test:wp-isolation`.
- Local `pnpm tdd` performs `git pull --ff-only` on the branch's tracking remote first; never assume it can upgrade a stale local feature branch to `dev`. On a fresh chat start from the **remote `dev` baseline** explicitly.

## Product and delivery constraints
- **WEM-8 is new installations only.** No legacy plugin compatibility, saved data migration, alias, dual-read or older shortcode support. See [naming contract](../architecture/naming-contract.md).
- **WEM-13 owns real actor/event authorization**; until verified, do not remove guest AJAX/shortcode/QR fail-closed entry guards. Do not claim public ticket lookup or check-in works.
- **WEM-12** owns known CSV import accounting discrepancy; **WEM-14** owns append-only movement-history design. The source-comparison inventory lives at [WEM-9 delta](../architecture/legacy-delta-v211-v221.md).
- Admin UX debts (mixed ES/EN, two event-selection interfaces, generic Custom Fields) and GitHub Actions Node/Ubuntu migration notices are deferred. No production deployment or real guest data was used during Foundation walkthroughs.
- PHPCS/PHPStan apply only to configured scopes, not necessarily all historical PHP sources. Review the configs before extending claims.

## Immediate next steps
1. Verify GitHub Actions for the **current post-merge `dev` SHA**, not only PR run #169. If new docs-only commits are made, verify their corresponding run as well.
2. Record the final verified `dev` SHA and CI result in Jira WEM-10/WEM-2; close WEM-10 then WEM-2 only when those gates pass.
3. Begin the next story in a fresh chat using `AGENTS.md`, `docs/README.md`, Foundation baseline, remote source and Jira. No additional Foundation feature work, new branches or migrations are implied by this handoff.
