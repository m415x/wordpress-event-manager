# Testing (Foundation)

This document describes the initial *standalone PHP unit-test harness*. It does not assert that the historical plugin boots or passes WordPress integration tests.

## Local requirements
- Docker Desktop running.
- WordPress environment started with `wp-env`.
- Work from the repository root in Bash.

## Installation (inside Docker)

```bash
wp-env run cli --env-cwd=wp-content/plugins/wordpress-event-manager composer install --no-interaction
```

`composer.lock` is tracked. When `composer.json` introduces or changes dependencies, update the lockfile explicitly and commit it. Do not commit `vendor/` or credentials.

For the WordPress integration bootstrap, WordPress Core requires PHPUnit Polyfills. The wp-env WordPress test framework currently invokes an API removed in PHPUnit 10. WEM-5 therefore uses PHPUnit `^9.6` with `yoast/phpunit-polyfills:^2.0` to match that framework (rather than claiming PHPUnit 11 compatibility). After pulling the manifest change, run this one-time full dependency reconciliation in the dedicated Docker CLI:

```bash
wp-env run cli --config=.wp-env.test.json --env-cwd=wp-content/plugins/wordpress-event-manager composer update --with-all-dependencies --no-interaction
```

Review and commit the resulting `composer.lock` so subsequent `composer install` restores exactly the tested dependency set.

## Focused standalone PHPUnit

```bash
wp-env run cli --env-cwd=wp-content/plugins/wordpress-event-manager vendor/bin/phpunit --testsuite unit
```

The initial smoke test verifies that the plugin entrypoint exists and declares ordinary WordPress header fields. It deliberately does **not** require/activate WordPress or instantiate the plugin. It is expected to be green even while the known historical plugin bootstrap defect remains unresolved.

## WordPress integration test harness
`wp-env` supplies the WordPress PHPUnit files at `WP_TESTS_DIR`. Run integration tests only against the separate disposable environment selected explicitly using `--config=.wp-env.test.json`; it has its own Docker containers and data, and never uses production credentials.

Start the dedicated test environment from the plugin root:

```bash
wp-env start --config=.wp-env.test.json
```

Then run the WordPress integration suite:

```bash
wp-env run cli --config=.wp-env.test.json --env-cwd=wp-content/plugins/wordpress-event-manager vendor/bin/phpunit -c phpunit.integration.xml.dist
```

The initial WordPress integration test uses WordPress' own `get_plugin_data()` API, but **does not activate** the historical plugin (known WEM-3 boot defect). The WordPress test framework may reset its dedicated disposable database; never point this command to the development or production database. User confirmed GREEN for the standalone unit and WordPress integration suites on 2026-10-01, with PHPUnit 9.6 + Polyfills 2.x. Counts and assertions for the integration run were not provided; Jira WEM-5 records the human confirmation. This validates the harness and plugin metadata read, not actual plugin activation.

The dedicated `.wp-env.test.json` explicitly sets `testsEnvironment: false`, because each configuration otherwise also launches a secondary test site on port 8889. The existing development configuration may already own that port. The dedicated WordPress environment uses port 8890 and its own Docker database; WordPress' PHP test bootstrap was confirmed operational in the isolated CLI integration run. Do not reset/destroy the development database.

## Compact RED/GREEN commands — WEM-6 (locally verified)

Run from the repository root in Git Bash after `git pull --ff-only`:

```bash
bash tests/Runner/tdd-runner.test.sh
bash scripts/tdd.sh red unit --filter SpecificContractTest
bash scripts/tdd.sh green unit --filter SpecificContractTest
bash scripts/tdd.sh green integration --filter WordPressPluginMetadataTest
bash scripts/tdd.sh diagnose integration --filter WordPressPluginMetadataTest
```

- `red` succeeds (exit 0) **only** when PHPUnit exits unsuccessfully with a reported assertion failure and no test errors or obvious bootstrap/fatal error. An unexpected PASS or infrastructure error makes `red` fail. The developer must still inspect the failed assertion to confirm it expresses the intended contract; the runner cannot infer semantics.
- `green` requires a PHPUnit `OK (...)` result followed by `php -l` syntax checks on the plugin entry point and PHP source/tests inside Docker.
- `diagnose` prints unabridged output and preserves the test exit code. Routine `red`/`green` prints a short summary, escalating errors to the last 35 log lines.
- `unit` uses `phpunit.xml.dist`; `integration` uses `phpunit.integration.xml.dist`, both in the dedicated `.wp-env.test.json` Docker setup.
- The Bash runner has eight mocked behavioral classification cases in `tests/Runner/tdd-runner.test.sh`; these test the runner's result handling, not PHP/WordPress behavior. **2026-10-01 human execution:** all 8/8 self-tests passed. Both `bash scripts/tdd.sh green unit` and `bash scripts/tdd.sh green integration` reported `GREEN confirmed: focused tests and PHP syntax verification passed.` A real new-feature behavioral RED has not yet been executed; do not imply otherwise.
- WEM-6 syntax verification is deliberately narrow. Full PHP style/static analysis, CI and cross-environment validation are WEM-7 scope. No remote CI pass is implied.

## Known bootstrap blocker
The existing plugin is intentionally **not** automatically loaded by the integration bootstrap; its activation errors belong to a separate bounded regression-and-fix story. A test that fails because the environment fails to initialize is not a valid behavioral RED.

## Known blockers
WEM-3 records the historical autoload filename mismatch and incomplete module hook registration. Neither problem is fixed by this harness.


## WEM-7 PHP quality / CI baseline (requires local and GitHub verification)

The Foundation deliberately separates **existing historical runtime debt** from gates on maintained code:
- PHP syntax: `sh scripts/check-php-syntax.sh` checks plugin entrypoint, all `includes/`, and PHP tests. This catches syntax failures but cannot establish correctness or WordPress API safety.
- Code style: `composer run lint:php` applies PHPCS PSR-12 to `tests/` (new PHP tests), not to the historical runtime code. Treat extension to production modules as an explicit migration after WEM-8 instead of silently rewriting old PHP.
- Static analysis: `composer run analyse:php` runs PHPStan level 0 over `tests/Unit/` (independent tests). WordPress-backed tests and plugin runtime are **outside** this initial PHPStan analysis scope and must be included later with WordPress stubs or compatible context.
- Unit tests: `composer run test:unit`.
- Test runner classifiers: `bash tests/Runner/tdd-runner.test.sh`.
- WordPress integration: `bash scripts/tdd.sh green integration`, using isolated `.wp-env.test.json`.

After updating `composer.json` in WEM-7, regenerate `composer.lock` in the isolated Docker CLI:

```bash
wp-env run cli --config=.wp-env.test.json --env-cwd=wp-content/plugins/wordpress-event-manager composer update --with-all-dependencies --no-interaction
```

Review `composer.lock` changes and commit them. From Git Bash verify:

```bash
wp-env run cli --config=.wp-env.test.json --env-cwd=wp-content/plugins/wordpress-event-manager composer validate --strict
wp-env run cli --config=.wp-env.test.json --env-cwd=wp-content/plugins/wordpress-event-manager sh scripts/check-php-syntax.sh
wp-env run cli --config=.wp-env.test.json --env-cwd=wp-content/plugins/wordpress-event-manager composer run lint:php
wp-env run cli --config=.wp-env.test.json --env-cwd=wp-content/plugins/wordpress-event-manager composer run analyse:php
bash tests/Runner/tdd-runner.test.sh
bash scripts/tdd.sh green unit
bash scripts/tdd.sh green integration
```

GitHub Actions workflow `.github/workflows/php-quality.yml` uses PHP 8.3, the tracked Composer lockfile, and an independently provisioned Docker/`wp-env` integration environment. Node and pnpm are CI setup only; this does not track the user's unreviewed local `package.json` or npm lockfile. CI installs a pinned `@wordpress/env` version. GitHub status is authoritative for actual CI outcome; **writing the workflow is not evidence that it passed**. If the GitHub runner's WordPress core test framework changes, diagnose and version a compatible test stack; do not hide failed CI gates.

Existing functional defects (plugin bootstrap and unauthenticated access) stay tracked in WEM-3 and are **not** declared fixed by these quality checks.

### Windows checkout line endings (WEM-7)

The project tracks `.gitattributes` to require LF for `*.php`, `*.sh` and configuration files. In Windows Git Bash, CRLF shell scripts can fail before PHP starts (e.g. `set: illegal option -`), and PHPCS PSR-12 rejects CRLF. From an updated branch, inspect with `git ls-files --eol scripts/check-php-syntax.sh tests/bootstrap.php`: the working-tree column should read `w/lf`. If it does not, reconcile the local checkout without overwriting personal changes; no global Git setting is required.

PHPCS applies namespaces and PSR-12 headers to new test files. `tests/integration-bootstrap.php` is deliberately a procedural WordPress fixture bootstrap and receives a narrowly scoped side-effects sniff exception. The historical plugin PHP remains syntax-checked but outside strict PHPCS/PHPStan coverage; this is explicit technical debt, not certification of the plugin.

Local execution 2026-10-01 before the LF/PSR corrections: Composer strict validation and PHPStan passed; PHP syntax script failed at shell startup and PHPCS reported CRLF/header/namespace issues. Fixes are committed, but local rerun and CI verification are still pending.

### WEM-7 verified CI baseline — 2026-10-01

GitHub Actions workflow run [#12](https://github.com/m415x/wordpress-event-manager/actions/runs/36873660470), commit `54be472512b6829f3410f4c5c36f31a3dc625fd5`, completed with overall conclusion **success**. Both jobs completed successfully:

- `PHP 8.3 lint, analysis and unit`: Composer strict validation and locked install, plugin-wide PHP syntax, PHPCS (new tests scope), PHPStan (independent unit tests scope), PHPUnit unit smoke and shell runner classification.
- `WordPress isolated integration`: Docker/`wp-env` bootstrap, Composer locked dependencies and WordPress PHPUnit integration.

Human-run verification also confirmed runner classification `8/8` and both compact `green unit` and `green integration` including their PHP syntax gate. Current tracked lock includes PHPUnit 9.6.37, Polyfills 2.0.5, PHPStan 2.2.16 and PHP_CodeSniffer 4.0.4.

**Deferred maintenance warnings (not test failures):** GitHub Actions reports Node.js 20 deprecation for action versions currently used, and an upcoming change of `ubuntu-latest` to Ubuntu 26 beginning 2026-10-19. Review action version compatibility and runner image in a separately scoped maintenance change; do not treat warnings as successful migration evidence.

WEM-7 does **not** attest that historical plugin activation, authorization or QR behavior is corrected; those remain bounded future work.


### Compact pnpm entrypoints — WEM-8 tooling refinement

The preferred interactive interface is now the **pnpm scripts**, rather than invoking the Bash runner with positional mode arguments:

```bash
pnpm tdd:red tests/Unit/NeutralIdentityContractTest.php
pnpm tdd tests/Unit/PluginEntrypointTest.php
pnpm tdd tests/Integration/WordPressPluginMetadataTest.php
pnpm tdd:red tests/Unit/NeutralIdentityContractTest.php -v
pnpm tdd tests/Unit/PluginEntrypointTest.php --verbose
pnpm test:runner
```

- `tdd:red`: prints **RED** only if PHPUnit reports an expected assertion failure and no bootstrap error. Returns zero for confirmed expected RED, nonzero for a pass or infrastructure error. This is the established WEM-6 contract, intentionally not the shell semantics of another project.
- `tdd`: prints **GREEN** only if focused PHPUnit and the PHP syntax gate both pass.
- Unanticipated failures print **ERROR**, without stack traces by default; add `-v` or `--verbose` after the test path to inspect the captured PHPUnit output and syntax logs.
- Path selection maps `tests/Unit/...` to standalone PHPUnit and `tests/Integration/...` to the isolated WordPress test config. Alternatively `pnpm tdd unit --filter TestMethod` remains supported.
- `pn` is **not a standard pnpm executable**. If the developer has a personal `pn` alias, it can work, but portable documentation uses `pnpm`.
- `package.json` contains only scripts. Do not run npm installation to operate these commands: the Bash runner uses `wp-env` from the developer's environment. Prefer the tracked pnpm dependency/lock discipline if JavaScript dependencies are added later.
- **Local untracked-file warning:** the user previously had an untracked local `package.json` and `package-lock.json`. Back up and inspect the local manifest before pulling the new tracked `package.json`, to avoid an untracked-file checkout conflict. Do not silently discard the local file.
- These refinements are **not locally verified yet**. The prior WEM-6 8/8 result applies to the former runner version. The updated runner fixture now defines 11 classification/verbosity checks; record actual results before claiming a new GREEN.


### Pre-test Git synchronization (WEM-8 refinement)

The interactive TDD runner now invokes `git pull --ff-only --quiet` on the current checked-out tracking branch **before** running PHPUnit. Therefore `pnpm tdd tests/Unit/GuestMetadataNamingTest.php` and `pnpm tdd:red ...` require only one command for synchronization and the test. A non-fast-forward, missing upstream or unavailable remote aborts before PHPUnit with `ERROR`, and `-v`/`--verbose` reveals the actual Git diagnostic. The runner never automatically merges with merge commits, rebases or resets.

CI intentionally skips the pull (`CI=true`) to keep the checked-out commit deterministic. To use the runner explicitly offline or during mock fixtures, set `WEM_TDD_SKIP_SYNC=1`. The runner self-test uses an injected temporary Git executable to verify the pull command without contacting the real remote.

The updated self-test specifies **15 cases** (the previous 11, plus successful sync, CI skip, explicit skip, and failed pull); the new checks are **not yet verified locally**. Do not claim that the newly integrated pre-test Git behavior passed until the developer reports a real execution.


## Disposable WordPress daily commands — WEM-11

Use **Git Bash** from the repository root with Docker Desktop running. These commands always use the tracked `.wp-env.test.json` (disposable WordPress on `http://localhost:8890`), never the untracked development `.wp-env.json` or production.

| Command | Meaning |
| --- | --- |
| `pnpm wp:start` | Start disposable WordPress; check whether the WEM plugin is already active, activate **only if needed**, and report current status. |
| `pnpm wp:status` | Show WordPress plugin status (requires a running environment). |
| `pnpm wp:verify:anon` | Send real HTTP POST requests to **both** protected guest AJAX routes on localhost:8890, require status **403** and exact deny-all JSON. Requires a running, activated environment. |
| `pnpm wp:stop` | Stop the disposable environment, preserving its database and files. |
| `pnpm test:wp-scripts` | Execute deterministic mocked command/HTTP classifications without Docker or production access. |
| `pnpm tdd tests/Integration/SensitiveAjaxFailClosedTest.php` | Run focused PHP/WordPress PHPUnit integration tests; distinct from actual HTTP verification. |

### Typical daily workflow

```bash
pnpm wp:start
pnpm wp:verify:anon
# Develop; run targeted pnpm tdd / pnpm tdd:red as needed.
pnpm wp:stop
```

- `wp:start` is safe to repeat. It does **not** delete/reinitialize databases, install Composer dependencies every time or force plugin reactivation if already active. wp-env's own persistent Docker volumes normally retain site state across stops and starts; if volumes are deleted or the environment is recreated, activation is needed again.
- `wp:verify:anon` tests **anonymous HTTP only**. It never obtains or stores login cookies, personal data or credentials. It does **not** satisfy the WEM-11 authenticated HTTP gate or all final integration gates.
- Neither `pnpm tdd` nor these helpers automatically run all full CI checks. Dependencies only need installation/reconciliation when first provisioning or when manifests/locks change.
- No destructive reset/delete commands are exposed through these helpers. Never point the smoke test to production.
- Before treating these scripts as verified, run `pnpm test:wp-scripts`, then `pnpm wp:status` and `pnpm wp:verify:anon` against the already running disposable site and record the actual result. Creating the scripts does not itself establish a passing gate.
