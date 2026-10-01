# Current operational handoff — WEM-2 Foundation

Status date: 2026-10-01. This is a work-in-progress handoff, not a completed-story record.

## Remote baseline
- Repository: `m415x/wordpress-event-manager`.
- Historical source baseline: `main@74116d06ccf40f3316cee7a33b034df45f8efa7b`.
- Integration branch: `dev`; active branch: `feat/WEM-2-foundation`.
- Project Jira: WEM. WEM-1 epic, WEM-2 story.
- WEM-3 completed a code inventory and stored findings in Jira. No product PHP modifications yet.
- WEM-4 is completed in Jira. The user pushed `.gitignore` via `bc2664b`; durable workflow documentation was subsequently added on the story branch.
- WEM-6 verification complete (pending Jira reconciliation in this handoff): 8/8 runner classification self-tests and GREEN for both unit and integration with PHP syntax checks.
- WEM-5 harness verification complete: human confirmed both standalone unit and WordPress integration GREEN after reconciling the WordPress test framework to PHPUnit 9.6 and Polyfills 2.x. Remote composer.lock currently pins PHPUnit 9.6.37 and Polyfills 2.0.5. WordPress plugin itself remains unverified for activation.

## Observed local environment
- Windows with Git Bash, Node.js, pnpm, Docker Desktop.
- User started `wp-env` with PHP 8.3; WordPress dev reported at localhost:8888 and test at localhost:8889. These are local ephemeral ports/environments.
- User ran `wp-env run cli php -v` successfully, obtaining PHP 8.3.35.
- User ran `wp-env run cli wp plugin list`; WordPress boot terminated with a missing-class fatal. No plugin activation pass has been demonstrated.
- `.wp-env.json`, `package.json`, `package-lock.json` were untracked locally at last user report; do not blindly add them. Reconcile and transition to pnpm in planned infrastructure task. Dedicated `.wp-env.test.json` is tracked remotely for integration isolation.

## Open blockers/debt
- Custom autoload filename mapping is inconsistent with class filenames.
- Six instantiated modules lack calls to hook/shortcode/menu setup functions. Tests must prove corrected wiring later.
- Anonymous AJAX handlers and nonce-only mutation guard are known security debt; avoid publishing legacy services publicly.
- Tickets are inferable and read/write access scoping is inadequate.
- Checkout/re-entry state is destructive, without full audit lineage.
- README makes unsupported claims about complete movement history and externally independent QR rendering.
- Legacy plugin identity has to be removed under WEM-8 with explicit compatibility/migration design; do not introduce its old identifiers into newly maintained files.

## Next work
1. Sync local feature branch with remote commits (`git pull --ff-only`).
2. WEM-6 is closed; runner classification self-tests passed 8/8, compact unit GREEN and integration GREEN both included PHP syntax verification (human execution, 2026-10-01). No true feature-level RED executed yet.
3. WEM-7 verification complete: GitHub Actions run #12 at 54be472 (2026-10-01) completed SUCCESS across both static/unit and isolated WordPress integration jobs. Composer lock is synced. Scope: PHPCS PSR-12 on tests, PHPStan level 0 on standalone unit tests, syntax all plugin PHP. Historical plugin activation still fails and is NOT a WEM-7 gate. Node.js 20 action deprecation and upcoming ubuntu-latest image migration are deferred maintenance warnings.
4. WEM-8 is En curso. User explicitly approved NEW INSTALLATIONS ONLY and breaking compatibility; no old data migration, alias or dual-read path. Contract: `docs/architecture/naming-contract.md`; expected-failure tests: `tests/Unit/NeutralIdentityContractTest.php`. These were committed BEFORE any runtime plugin changes; their actual RED is pending human execution. CI may intentionally fail until the rename is implemented and verified. Do not treat this as a harness regression.
5. WEM-9 legacy delta; WEM-10 closure and `dev` integration.
6. Preserve observed RED vs source-only findings vs genuinely executed verification.

Consult `AGENTS.md`, `docs/README.md`, current repository state, and full Jira tasks before changing scope.
