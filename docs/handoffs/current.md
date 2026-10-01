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
4. WEM-8 is En curso. User explicitly approved NEW INSTALLATIONS ONLY and breaking compatibility; no old data migration, alias or dual-read path. Contract: `docs/architecture/naming-contract.md`; expected-failure tests: `tests/Unit/NeutralIdentityContractTest.php`. These were committed BEFORE any runtime plugin changes. Human confirmed expected RED for `NeutralIdentityContractTest` (2026-10-01). First bounded implementation slice now committed: new `wordpress-event-manager.php` entrypoint, old entrypoint deleted, eight class declarations changed to transitional `WEM_` and existing test/syntax command references updated. User subsequently confirmed focused GREEN for canonical entrypoint plus expected source-scan RED (1 test, 33 assertions, 1 failure). Next, user requested compact pnpm TDD experience: added package.json scripts, runner emits RED/GREEN/ERROR with -v/--verbose details and supports direct tests/Unit or tests/Integration paths; runner fixtures expanded to 11 mock cases. THESE NEW TOOLING CHANGES HAVE NOT BEEN LOCALLY RUN, so their verification is pending. Local user had an untracked package.json and package-lock.json before the new tracked package.json was created remotely: preserve or back up local files before git pull. Remaining legacy helpers, postmeta, hooks, shortcodes, JS/CSS and readme still require migration; whole CI is expected RED until completed. Historical underscore/autoloader and hook-wiring defects remain unverified/known. Do not treat this as a harness regression.
5. WEM-9 legacy delta; WEM-10 closure and `dev` integration.
6. Preserve observed RED vs source-only findings vs genuinely executed verification.

Consult `AGENTS.md`, `docs/README.md`, current repository state, and full Jira tasks before changing scope.

## WEM-8 next RED — 2026-10-01
- Human confirmed updated Bash TDD runner mock suite 11/11, focused canonical entrypoint GREEN, whole naming source scan expected RED. These are Bash executions; pnpm script invocation is not yet separately verified.
- Next focus: `tests/Unit/GuestMetadataNamingTest.php` committed at `c261da49` before changing any guest metadata consumer. It asserts canonical helper names and post-meta keys across six source modules. Execute `pnpm tdd:red tests/Unit/GuestMetadataNamingTest.php`; only then rename this bounded PHP consumer group. Other shortcodes, JavaScript and CSS may still be RED afterwards.

## WEM-8 guest metadata rename — pending focused GREEN
- User confirmed `pnpm tdd:red tests/Unit/GuestMetadataNamingTest.php` reported expected RED before implementation (no exact test counts supplied).
- Updated all six PHP files listed in `GuestMetadataNamingTest` to use `wem_` helper names, postmeta keys, field names and related identifiers (remote commits 05cbf1d, f022f3e, 407d2f9, 48b0b61, e3b7040, 3ce97db). Remote readback shows zero banned short-prefix matches across these six modules. **This is source inspection only**, not executed PHPUnit GREEN.
- Next: local `git pull --ff-only`, then `pnpm tdd tests/Unit/GuestMetadataNamingTest.php` and report output. Whole-project identity test remains expected RED because QR module, assets, readme and other surfaces remain. HTML identifiers emitted by PHP must be reconciled with JavaScript and CSS in subsequent bounded work; no end-to-end UI correctness asserted.

## WEM-8 runner refinement — automatic sync
- User confirmed focused `GuestMetadataNamingTest` GREEN after the six PHP metadata-consumer edits. Whole-project identity scan remains intentionally RED pending QR/JS/CSS/readme migration.
- The user requested a one-line local TDD command that includes `git pull --ff-only`. `scripts/tdd.sh` now executes `git pull --ff-only --quiet` before PHPUnit for interactive invocations. It aborts with ERROR on failure (verbose shows Git diagnostics); CI and explicitly opted-out offline runs do not pull. Script changed at `28a1aa5` and runner self-tests now include 15 scenarios at `2ef4b5d` (new checks pending local execution).
- After this remote documentation update, use `pnpm test:runner` to verify 15/15, then `pnpm tdd tests/Unit/GuestMetadataNamingTest.php`. No manual `git pull` is needed before `pnpm tdd`, but `pnpm test:runner` alone intentionally does not synchronize.

## WEM-8 current — 2026-10-01
- User reported `green` after the new automatic-sync runner verification request. The response did not include individual command logs/counts, so the acceptance is user-reported rather than independently measured. Auto sync performs fast-forward pull only for interactive TDD; CI remains deterministic.
- Pending next bounded RED: `tests/Unit/FrontendIdentityContractTest.php` committed `1640885` before any new frontend/QR changes. It covers canonical QR shortcodes and consistent WEM identifiers/selectors across QR/shortcodes/AJAX/front-end JS and CSS. Run `pnpm tdd:red tests/Unit/FrontendIdentityContractTest.php` (it pulls automatically). Do not rename the frontend yet without reported RED.
- Complete global naming verification still waits for README and remaining surfaces. Prior historical runtime activation and authorization defects remain outside this naming slice.

## WEM-8 frontend identity slice — awaiting focused GREEN
- Human confirmed expected `RED` for `pnpm tdd:red tests/Unit/FrontendIdentityContractTest.php` before edits (no full output supplied).
- Corrected the test's selector assertions to inspect `includes/class-ajax-handler.php`, which actually produces the table-row markup, instead of the shortcode controller (commit `8e9f851`).
- Coordinated names in `includes/class-qr-generator.php`, `assets/css/frontend.css`, `assets/css/admin.css`, `assets/js/frontend.js`, and shortcode template classes at `includes/class-shortcode-manager.php`. New QR tags `wem_qr_table`/`wem_qr_single`; CSS class selectors `wem-*`; JS `WEM`/`wem_ajax`; existing HTML ids and form/meta keys remain `wem_*` (commits a61e1fe, 6c53a23, 30800b6, ae7d8d4, 220e2ca).
- Remote readback checked these modified files for the forbidden historical prefix: zero occurrences. This is **source inspection**, not a runtime or test pass. The existing JS configuration global was not proven enqueued/localized by WordPress; missing hook registration and unsafe anonymous AJAX remain WEM-3 debt, not solved by this rename.
- Next run exactly `pnpm tdd tests/Unit/FrontendIdentityContractTest.php`; local runner automatically pulls. If ERROR, use `--verbose`. Even after focused GREEN, whole-project identity scan remains expected RED due to historical readme; do not close WEM-8.

## WEM-8 identity-wide verification — pending
- Human reported GREEN for `pnpm tdd tests/Unit/FrontendIdentityContractTest.php` after coordinated QR, JS and CSS rename. Exact test count not supplied; no plugin activation or UI claim.
- Replaced old product `readme.txt` with a neutral development-only readme (commit f5a8ea9). Current document states new installations only, source-level shortcode names without end-to-end verification, external QR image dependency, and legacy functional/security debt. No historical brand or aliases intentionally retained.
- GitHub recursive repository tree at commit f5a8ea9 contains 39 tracked files. Comprehensive per-file remote scan reached a connector call limit; **do not claim zero legacy identifiers yet**. Next verification is existing `NeutralIdentityContractTest` run in local checkout via `pnpm tdd tests/Unit/NeutralIdentityContractTest.php`, which includes a recursive scan and reports actual assertions.
- Remain in WEM-8 En curso. Even if global naming passes, test activation/autoloader and functional hooks are separately unresolved; follow acceptance criteria and WEM-3 debt rather than closing on source-only evidence.

## WEM-8 final naming check and CI style follow-up — 2026-10-01
- User confirmed `pnpm tdd tests/Unit/NeutralIdentityContractTest.php` returned GREEN. This is the locally executed global maintained-source identity assertion, not evidence of functional activation.
- GitHub Actions run 36880226491 at 7a43e90 had isolated WordPress integration SUCCESS, but PHP static/unit job FAILED specifically at PHPCS. Read job logs: six PSR-12 multi-line foreach syntax errors in `tests/Unit/GuestMetadataNamingTest.php` and one 128-character line warning in `tests/Unit/NeutralIdentityContractTest.php`. Corrected formatting only via commits 4e999c1 and 4f5ba9d; no test logic changed.
- These formatting changes still require local PHP lint/unit verification and new GitHub Actions outcome; do not close WEM-8 until verified. Future steps include assessing WEM-3 plugin bootstrap and hook initialization debt separately before any user-visible production claim.

## WEM-8 CI runner fixture follow-up
- User confirmed `pnpm tdd unit` GREEN after formatting fixes. GitHub Actions run 36880687235: PHP 8.3 Composer, syntax, PHPCS, PHPStan and PHPUnit passed (8 tests, 98 assertions); runner self-test failed at `auto_sync`: mock logs full `pull --ff-only --quiet` while test incorrectly expected `--ff-only --quiet`. This is a fixture assertion error, not actual Git synchronization failure. WordPress integration was still running at initial review.
- Corrected the mock's expected full command in `tests/Runner/tdd-runner.test.sh` commit cc3281f. Follow-up Actions pass is still unverified; do not mark WEM-8 complete before latest CI results and scope review. No product code changed.

## Runner escaped-newline correction — pending human GREEN
- User ran runner self-tests after cc3281f: first 14 cases PASS; last `failed_pull` FAIL because actual captured output was literal `ERROR\\n`, not `ERROR` with newline. Root cause: the two error-path `printf` statements for pretest Git synchronization used over-escaped newline sequences. Updated only those two print statements in `scripts/tdd.sh` at bdd5672; no Git sync or PHPUnit semantics changed.
- The explicit `failed_pull` self-test provided the RED. Next: run `pnpm tdd unit` to pull the latest script, then `pnpm test:runner`. Expected `GREEN` and 15/15 PASS respectively. Do not claim a GREEN until user confirmation. GitHub CI must subsequently be verified separately.

## WEM-8 formal verification and handoff — 2026-10-01
- Canonical identity contract `docs/architecture/naming-contract.md` implemented with explicit new-installations-only compatibility rupture: no backfill, historical entrypoint, metadata aliases, hook aliases or shortcode aliases.
- Human verification: focused metadata GREEN, frontend identity GREEN, recursive maintained-source identity GREEN, full unit GREEN, and runner classification **15/15 PASS**, including simulated failed pull and `--verbose` behavior.
- GitHub Actions run [#36881129249](https://github.com/m415x/wordpress-event-manager/actions/runs/36881129249) against runtime/test commit `bdd567282f78d394ce8dd181b9816394349a2d08` completed **SUCCESS**. Both `PHP 8.3 lint, analysis and unit` and `WordPress isolated integration` jobs completed SUCCESS. This run predates this documentation-only handoff update; do not misattribute it to later documentation commits.
- Historical shortcodes, branded metadata and readme replaced on the maintained surface; scanner and CI verified. Existing unbranded CPT `invitado` and taxonomy `evento` deliberately retained for domain continuity, not to support historical installations.
- No assertion that WordPress plugin activation, autoloader path mapping, module initialization, access controls, QR service reliability or check-in history are correct. WEM-3 recorded outstanding defects, for subsequent explicit stories rather than silently treating them as fixed by WEM-8.
- GitHub Actions nonblocking warnings: Node.js 20 deprecation in action versions and runner image transition for `ubuntu-latest` beginning 2026-10-19. Track as separate CI maintenance; not a WEM-8 naming failure.
- Next stage within WEM-2: WEM-9 reconcile historical snapshots and source behavior; WEM-10 foundation closure. No merge to dev solely from closing this subtask.

## WEM-9 source comparison — 2026-10-01, analysis awaiting review
- Jira WEM-9 was transitioned from Por hacer to **En curso**. WEM-2 remains En curso; WEM-8 is Listo. Stay on `feat/WEM-2-foundation`, do not create subtasks/branches or merge.
- Source for earlier baseline was located in the user-provided Library/conversation file `mapeo_proyecto(1).txt`: it includes the **v2.1.1 monolithic PHP plugin source** and its own historical readme. It is not in current git history. Its specific historical identity remains referenced through Jira, not copied into modern code.
- Compared its PHP registration, guest/CPT/taxonomy, shortcode, AJAX, query/filter, CSV import/export and data-model shape against current modular PHP after WEM-8. Durable **draft** classification matrix: `docs/architecture/legacy-delta-v211-v221.md` (3dd4f10); docs index updated (15242a3).
- Concrete distinctions: earlier readme said CSV `ticket` but actual import/export source already uses `titulo` (documentation discrepancy); modular CSV import counter always labels a successful existing-row update as `created` (apparent regression, source-only); modular autoloader converts prefixes but not filename underscores/hyphens and six module setup methods aren't called from entrypoint (source-level activation/hook regression versus monolithic global registrations); QR table/image shortcodes and pre-import encoding conversion are added; anonymous AJAX and nonce-only authorization and destructive current-state metadata are preserved technical/security debt.
- These are **source-backed classifications, not an execution test**. The only established current CI is GitHub run #36881129249 for the naming/test baseline; no activation test, guest action authorization proof or updated implementation GREEN is claimed.
- Next: user review of WEM-9 matrix, agree whether to record regression/security follow-up as separate Jira work or leave solely as documented backlog. Do not fix runtime behavior in WEM-9 without distinct explicit Jira authorization. Close WEM-9 only after findings are reconciled and accepted; WEM-10 later closes parent story.

## WEM-9 approved Jira reconciliation — 2026-10-01
- User approved creating independent follow-up work and evaluating whether plugin activation is required before WEM-2 closure.
- Created Jira issues: WEM-11 (autoload + module hooks + WordPress activation), WEM-12 (CSV import created/updated/skipped regression), WEM-13 (event-scoped authorization/security), WEM-14 (immutable guest movement history). These are **top-level separate Jira issues**, not additional WEM-2 subtasks, and have no implementation commits.
- Linked **WEM-11 Blocks WEM-10**. Other links are Relates: WEM-11 ↔ WEM-2, WEM-12 ↔ WEM-9 and WEM-2, WEM-13 ↔ WEM-11 and WEM-2, WEM-14 ↔ WEM-13.
- WEM-2/WEM-10 **cannot close** on existing metadata-only integration GREEN: initial actual plugin boot failed, and its modular hook setup differs from the earlier monolith. WEM-11 must supply real activation/registration integration and applicable walkthrough evidence.
- WEM-13 protects public endpoint exposure; do not assume safe actions based on plugin activation. WEM-12's observed regression remains open and requires explicit residual-risk/acceptance review at WEM-10. WEM-14 is future model design work, not foundation blocker.
- WEM-9 delta matrix updated: `docs/architecture/legacy-delta-v211-v221.md` commit 201cb45; no PHP/JS functional code changed. Its documentation scope is ready for review/closure, but do not start implementation of follow-ups without separate bounded RED-first approval.

## WEM-9 formal closure — 2026-10-01
- WEM-9 was transitioned to **Listo** in Jira after the user's explicit approval of separate follow-up tracking and activation-blocker evaluation. The source-classification document has been updated; no runtime implementation was made during WEM-9.
- Approved follow-ups created and linked: **WEM-11** (bootstrap and six missing module registrations, BLOCKS WEM-10), **WEM-12** (CSV importer created/updated counts), **WEM-13** (event-scoped authorization), **WEM-14** (immutable checkin history).
- **WEM-10 remains Por hacer and WEM-2 remains En curso**, and the feature branch `feat/WEM-2-foundation` remains unmerged. The previously verified CI covered *plugin metadata* WordPress integration, not activation or end-to-end feature behavior.
- Separate blocking issue WEM-11 requires approval of an implementation approach and focused activation RED before touching runtime; no automatic start here. Never expose unauthorized AJAX paths as safe solely by enabling hooks. WEM-12's regression requires an explicit decision at WEM-10 acceptance rather than silent waiver.
- Docs index updated to reflect WEM-9 completion. Next implementation handoff: inspect WEM-11 and security dependency WEM-13, determine whether safe boot-only registration can be tested without exposing sensitive anonymous endpoints, obtain explicit bounded implementation agreement, then TDD RED→GREEN.
