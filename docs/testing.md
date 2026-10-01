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
