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
3. WEM-7 is in progress: composer.json adds PHPCS ^4 and PHPStan ^2; lint is PSR-12 limited to new PHP tests, PHPStan is level 0 scoped to independent unit tests, and full-source syntax gate remains. GitHub Actions workflow added but CI has not been verified. Human must update and commit composer.lock, run new static/unit/integration gates and inspect actual CI results before WEM-7 closure.
4. WEM-8 identity migration; WEM-9 legacy delta; WEM-10 closure and `dev` integration.
5. Preserve observed RED vs source-only findings vs genuinely executed verification.

Consult `AGENTS.md`, `docs/README.md`, current repository state, and full Jira tasks before changing scope.
